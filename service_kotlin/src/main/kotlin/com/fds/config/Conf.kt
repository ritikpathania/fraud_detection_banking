package com.fds.config

object Conf {
    private fun env(name: String) = System.getenv(name)

    val mongoUri: String = env("MONGODB_URI")
        ?: error("MONGODB_URI is required")

    val mongoDb: String = env("MONGODB_DB") ?: "fraud_banking"

    // Base URL ONLY (no trailing slash, no /check_fraud at the end)
    private val pyFraudBase: String =
        (env("PY_FRAUD_URL") ?: "http://localhost:8001").trimEnd('/')

    // Expose a single canonical URL your code can use directly:
    val pyFraudUrl: String = "$pyFraudBase/check_fraud"

    val fraudThreshold: Double = env("FRAUD_THRESHOLD")?.toDoubleOrNull() ?: 0.80
    val httpTimeoutMs: Long = env("HTTP_TIMEOUT_MS")?.toLongOrNull() ?: 2000L
}
