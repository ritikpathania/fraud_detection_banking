package com.fds.domain

import kotlinx.serialization.Serializable

@Serializable
data class BankAccount(
    val _id: String,            // e.g. "ACC123"
    val currency: String,       // "INR"
    val balanceMinor: Long,     // paise
    val version: Long = 0L,
    // store timestamps as millis to avoid codec issues
    val createdAtMs: Long = 0L,
    val updatedAtMs: Long = 0L
)

@Serializable
enum class TxnStatus { PENDING, POSTED, BLOCKED, FAILED }

@Serializable
data class Txn(
    val _id: String,            // uuid
    val fromAccountId: String,
    val toAccountId: String,
    val amountMinor: Long,      // paise
    val currency: String,
    val status: TxnStatus,
    val idempotencyKey: String? = null,
    val createdAtMs: Long = 0L,
    val updatedAtMs: Long = 0L,
    val fraudScore: Double? = null,
    val fraudReasons: List<String>? = null
)

@Serializable
data class Idempotency(
    val _id: String,            // header value
    val requestHash: String,
    val responseJson: String,   // serialized TransferResultPayload
    val createdAtMs: Long = 0L,
    val expiresAtMs: Long = 0L
)

@Serializable
data class FraudAudit(
    val _id: String,            // uuid
    val transactionId: String,
    val score: Double,
    val action: String,         // "ALLOW" | "BLOCK"
    val reasons: List<String>,
    val modelVersion: String,
    val createdAtMs: Long = 0L
)

/** Used by transferRoute for idempotent caching / responses */
@Serializable
data class TransferResultPayload(
    val status: String,                 // "posted" | "blocked" | "error"
    val transaction_id: String,
    val currency: String,
    val fraud_score: Double? = null,
    val reasons: List<String>? = null,
    val from_balance_after: String? = null,
    val to_balance_after: String? = null
)