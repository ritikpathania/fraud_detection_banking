package com.fds.routes

import com.fds.db.Mongo
import io.ktor.server.routing.*
import io.ktor.server.application.*
import io.ktor.server.response.*
import io.ktor.http.*
import com.mongodb.client.model.Filters
import kotlinx.coroutines.flow.firstOrNull
import kotlinx.serialization.Serializable
import org.bson.Document
import com.mongodb.kotlin.client.coroutine.MongoCollection

@Serializable
data class BalanceResponse(
    val account_id: String,
    val balance: String,   // "123.45"
    val currency: String
)

private fun toMajorString(minor: Long?): String =
    "%.2f".format(((minor ?: 0L).toDouble()) / 100.0)

fun Route.balanceRoute() {
    get("/balance") {
        val id = call.request.queryParameters["account_id"]
            ?: return@get call.respond(HttpStatusCode.BadRequest, mapOf("error" to "missing_account_id"))

        // 1) Try the typed read (BankAccount)
        try {
            val acct = Mongo.accounts.find(Filters.eq("_id", id)).firstOrNull()
            if (acct != null) {
                return@get call.respond(
                    BalanceResponse(
                        account_id = id,
                        balance = toMajorString(acct.balanceMinor),
                        currency = acct.currency
                    )
                )
            }
        } catch (e: Throwable) {
            // fall through to raw mode
        }

        // 2) Fallback: raw Document read + coercion
        try {
            val rawCol: MongoCollection<Document> = Mongo.db.getCollection("accounts")
            val raw = rawCol.find(Filters.eq("_id", id)).firstOrNull()
                ?: return@get call.respond(HttpStatusCode.NotFound, mapOf("error" to "account_not_found"))

            val currency = (raw.get("currency") as? String) ?: "INR"
            val minor = when (val v = raw.get("balanceMinor")) {
                is Number -> v.toLong()
                is String -> v.toLongOrNull() ?: 0L
                else -> 0L
            }

            return@get call.respond(
                BalanceResponse(
                    account_id = id,
                    balance = toMajorString(minor),
                    currency = currency
                )
            )
        } catch (e: Throwable) {
            e.printStackTrace()
            val reason = (e::class.simpleName ?: "error") + (e.message?.let { ": $it" } ?: "")
            return@get call.respond(
                HttpStatusCode.InternalServerError,
                mapOf("error" to "balance_failed", "reason" to reason)
            )
        }
    }
}
