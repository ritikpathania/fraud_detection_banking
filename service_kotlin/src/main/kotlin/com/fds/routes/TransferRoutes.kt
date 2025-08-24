package com.fds.routes

import kotlinx.coroutines.flow.firstOrNull
import com.fds.config.Conf
import com.fds.db.Mongo
import com.fds.domain.*
import com.fds.util.toMinor
import com.mongodb.client.model.Filters
import com.mongodb.client.model.Updates
import io.ktor.http.*
import io.ktor.server.application.*
import io.ktor.server.request.*
import io.ktor.server.response.*
import io.ktor.server.routing.*
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import java.security.MessageDigest
import java.util.UUID

@Serializable
data class TransferReq(
    val from_account: String,
    val to_account: String,
    val amount: String,
    val currency: String,
    val metadata: Map<String, String>? = null
)

private fun sha256(s: String) =
    MessageDigest.getInstance("SHA-256").digest(s.toByteArray()).joinToString("") { "%02x".format(it) }

private fun major(minor: Long?): String = "%.2f".format(((minor ?: 0L).toDouble()) / 100.0)

/** fraudCheck returns Pair(score, reasons) */
fun Route.transferRoute(
    fraudCheck: suspend (TransferReq) -> Pair<Double, List<String>>
) {
    post("/transfer") {
        val idemKey = call.request.headers["Idempotency-Key"] ?: UUID.randomUUID().toString()
        val body = call.receive<TransferReq>()
        val reqHash = sha256("${body.from_account}|${body.to_account}|${body.amount}|${body.currency}")
        val nowMs = System.currentTimeMillis()
        val txId = "txn_${UUID.randomUUID()}"

        // --- Idempotency cache (fast path) ---
        val cached = Mongo.idempotency.find(Filters.eq("_id", idemKey)).firstOrNull()
        if (cached != null) {
            call.respondText(cached.responseJson, ContentType.Application.Json)
            return@post
        }


        // --- Parse amount ---
        val amtMinor = try {
            toMinor(body.amount)
        } catch (_: Throwable) {
            val err = TransferResultPayload("error", txId, body.currency, reasons = listOf("invalid_amount"))
            val j = Json.encodeToString(TransferResultPayload.serializer(), err)
            Mongo.idempotency.insertOne(
                Idempotency(idemKey, reqHash, j, createdAtMs = nowMs, expiresAtMs = nowMs + 86_400_000)
            )
            return@post call.respond(HttpStatusCode.BadRequest, err)
        }

        // --- Fraud check ---
        val (score, reasons) = fraudCheck(body)
        val threshold = Conf.fraudThreshold

        // --- BLOCKED path ---
        if (score >= threshold) {
            val blocked = TransferResultPayload(
                status = "blocked",
                transaction_id = txId,
                currency = body.currency,
                fraud_score = score,
                reasons = reasons
            )

            Mongo.transactions.insertOne(
                Txn(
                    _id = txId,
                    fromAccountId = body.from_account,
                    toAccountId = body.to_account,
                    amountMinor = amtMinor,
                    currency = body.currency,
                    status = TxnStatus.BLOCKED,
                    idempotencyKey = idemKey,
                    createdAtMs = nowMs,
                    updatedAtMs = nowMs,
                    fraudScore = score,
                    fraudReasons = reasons
                )
            )
            Mongo.fraudAudit.insertOne(
                FraudAudit(
                    _id = UUID.randomUUID().toString(),
                    transactionId = txId,
                    score = score,
                    action = "BLOCK",
                    reasons = reasons,
                    modelVersion = System.getenv("MODEL_VERSION") ?: "rules-v1",
                    createdAtMs = nowMs
                )
            )

            val j = Json.encodeToString(TransferResultPayload.serializer(), blocked)
            Mongo.idempotency.insertOne(
                Idempotency(idemKey, reqHash, j, createdAtMs = nowMs, expiresAtMs = nowMs + 86_400_000)
            )
            call.respondText(j, ContentType.Application.Json)
            return@post
        }

        // --- ALLOW path (no multi-doc tx; best-effort rollback) ---
        try {
            // debit with preconditions
            val debit = Mongo.accounts.updateOne(
                Filters.and(
                    Filters.eq("_id", body.from_account),
                    Filters.eq("currency", body.currency),
                    Filters.gte("balanceMinor", amtMinor)
                ),
                Updates.combine(
                    Updates.inc("balanceMinor", -amtMinor),
                    Updates.set("updatedAtMs", nowMs),
                    Updates.inc("version", 1L)
                )
            )
            if (debit.modifiedCount != 1L) {
                val err = TransferResultPayload("error", txId, body.currency, reasons = listOf("insufficient_funds_or_account"))
                val j = Json.encodeToString(TransferResultPayload.serializer(), err)
                Mongo.idempotency.insertOne(
                    Idempotency(idemKey, reqHash, j, createdAtMs = nowMs, expiresAtMs = nowMs + 86_400_000)
                )
                return@post call.respond(HttpStatusCode.BadRequest, err)
            }

            // credit
            val credit = Mongo.accounts.updateOne(
                Filters.and(
                    Filters.eq("_id", body.to_account),
                    Filters.eq("currency", body.currency)
                ),
                Updates.combine(
                    Updates.inc("balanceMinor", amtMinor),
                    Updates.set("updatedAtMs", nowMs),
                    Updates.inc("version", 1L)
                )
            )
            if (credit.modifiedCount != 1L) {
                // rollback debit (best effort)
                Mongo.accounts.updateOne(
                    Filters.eq("_id", body.from_account),
                    Updates.combine(
                        Updates.inc("balanceMinor", amtMinor),
                        Updates.inc("version", 1L)
                    )
                )
                val err = TransferResultPayload("error", txId, body.currency, reasons = listOf("dest_account_missing"))
                val j = Json.encodeToString(TransferResultPayload.serializer(), err)
                Mongo.idempotency.insertOne(
                    Idempotency(idemKey, reqHash, j, createdAtMs = nowMs, expiresAtMs = nowMs + 86_400_000)
                )
                return@post call.respond(HttpStatusCode.BadRequest, err)
            }

            // balances AFTER posting (declare once here only)
            val fromAfterMinor = Mongo.accounts.find(Filters.eq("_id", body.from_account)).firstOrNull()?.balanceMinor
            val toAfterMinor   = Mongo.accounts.find(Filters.eq("_id", body.to_account)).firstOrNull()?.balanceMinor

            // record posted + audit
            Mongo.transactions.insertOne(
                Txn(
                    _id = txId,
                    fromAccountId = body.from_account,
                    toAccountId = body.to_account,
                    amountMinor = amtMinor,
                    currency = body.currency,
                    status = TxnStatus.POSTED,
                    idempotencyKey = idemKey,
                    createdAtMs = nowMs,
                    updatedAtMs = nowMs,
                    fraudScore = score,
                    fraudReasons = reasons
                )
            )
            Mongo.fraudAudit.insertOne(
                FraudAudit(
                    _id = UUID.randomUUID().toString(),
                    transactionId = txId,
                    score = score,
                    action = "ALLOW",
                    reasons = reasons,
                    modelVersion = System.getenv("MODEL_VERSION") ?: "rules-v1",
                    createdAtMs = nowMs
                )
            )

            // response
            val posted = TransferResultPayload(
                status = "posted",
                transaction_id = txId,
                currency = body.currency,
                fraud_score = score,
                from_balance_after = major(fromAfterMinor),
                to_balance_after   = major(toAfterMinor)
            )

            // cache + respond
            val j = Json.encodeToString(TransferResultPayload.serializer(), posted)
            Mongo.idempotency.insertOne(
                Idempotency(idemKey, reqHash, j, createdAtMs = nowMs, expiresAtMs = nowMs + 86_400_000)
            )
            call.respondText(j, ContentType.Application.Json)
            return@post

        } catch (e: Exception) {
            e.printStackTrace()
            val err = TransferResultPayload(
                "error", txId, body.currency,
                reasons = listOf("internal_error", e::class.simpleName ?: "Exception", (e.message ?: "").take(180))
            )
            val j = Json.encodeToString(TransferResultPayload.serializer(), err)
            Mongo.idempotency.insertOne(
                Idempotency(idemKey, reqHash, j, createdAtMs = nowMs, expiresAtMs = nowMs + 86_400_000)
            )
            return@post call.respond(HttpStatusCode.InternalServerError, err)
        }
    }
}