package com.fds.routes

import com.fds.db.Mongo
import com.fds.domain.FraudAudit
import io.ktor.server.routing.*
import io.ktor.server.application.*
import io.ktor.server.response.*
import io.ktor.http.*
import com.mongodb.client.model.Filters
import kotlinx.coroutines.flow.firstOrNull
import kotlinx.coroutines.flow.toList
import kotlinx.serialization.Serializable

@Serializable
data class AuditListResponse(
    val items: List<FraudAudit>,
    val count: Int
)

fun Route.auditRoutes() {
    route("/audits") {
        get {
            // Query params
            val txnId    = call.request.queryParameters["transaction_id"]
            val action   = call.request.queryParameters["action"]?.uppercase() // ALLOW/BLOCK
            val minScore = call.request.queryParameters["min_score"]?.toDoubleOrNull()
            val sinceMs  = call.request.queryParameters["since_ms"]?.toLongOrNull()
            val untilMs  = call.request.queryParameters["until_ms"]?.toLongOrNull()
            val limit    = call.request.queryParameters["limit"]?.toIntOrNull()?.coerceIn(1, 100) ?: 20
            val skip     = call.request.queryParameters["skip"]?.toIntOrNull()?.coerceAtLeast(0) ?: 0

            // Build filter
            val parts = mutableListOf<org.bson.conversions.Bson>()
            if (txnId != null) parts += Filters.eq("transactionId", txnId)
            if (action != null) parts += Filters.eq("action", action)
            if (minScore != null) parts += Filters.gte("score", minScore)
            if (sinceMs != null) parts += Filters.gte("createdAtMs", sinceMs)
            if (untilMs != null) parts += Filters.lte("createdAtMs", untilMs)

            val filter = if (parts.isEmpty()) Filters.empty() else Filters.and(parts)

            // Simple in-memory sort/slice for now (small volumes)
            val all = Mongo.fraudAudit.find(filter).toList()
            val page = all.sortedByDescending { it.createdAtMs }.drop(skip).take(limit)

            call.respond(AuditListResponse(page, page.size))
        }

        // Convenience: GET /audits/:txnId
        get("/{transaction_id}") {
            val txnId = call.parameters["transaction_id"]!!
            val item = Mongo.fraudAudit.find(Filters.eq("transactionId", txnId)).firstOrNull()
                ?: return@get call.respond(HttpStatusCode.NotFound, mapOf("error" to "not_found"))
            call.respond(item)
        }
    }
}
