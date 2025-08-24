package com.fds

import com.fds.config.Conf
import com.fds.routes.auditRoutes
import com.fds.routes.balanceRoute
import com.fds.routes.transferRoute

// ---- Ktor HTTP client (fraud call) ----
import io.ktor.http.*
import io.ktor.client.HttpClient
import io.ktor.client.engine.cio.CIO
import io.ktor.client.plugins.HttpTimeout
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation as ClientContentNegotiation
import io.ktor.client.request.post
import io.ktor.client.request.setBody   // <-- add
import io.ktor.client.call.body
import io.ktor.http.contentType
import io.ktor.serialization.kotlinx.json.json as clientJson
import io.ktor.serialization.kotlinx.json.json
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json

// ---- Ktor server ----
import io.ktor.server.application.*
import io.ktor.server.engine.embeddedServer
import io.ktor.server.netty.Netty
import io.ktor.server.plugins.contentnegotiation.ContentNegotiation
import io.ktor.server.plugins.statuspages.StatusPages
import io.ktor.server.response.*
import io.ktor.server.routing.*

// server-side JSON plugin function
import io.ktor.serialization.kotlinx.json.json
import kotlinx.serialization.json.Json as ServerJson

// ----------------- HTTP client (timeouts + JSON) -----------------
val http = HttpClient(CIO) {
    install(ClientContentNegotiation) {
        clientJson(Json { ignoreUnknownKeys = true })
    }
    install(HttpTimeout) {
        requestTimeoutMillis = Conf.httpTimeoutMs
        connectTimeoutMillis = Conf.httpTimeoutMs
        socketTimeoutMillis  = Conf.httpTimeoutMs
    }
}

// ----------------- Models used ONLY for fraud call -----------------
@Serializable
private data class PyFraudReq(
    val transaction_id: String,
    val account: String,
    val amount: Double,
    val currency: String,
    val metadata: Map<String, String>? = null
)

@Serializable
private data class PyFraudResp(
    val transaction_id: String? = null,
    val fraud_score: Double = 0.0,
    val is_fraud: Boolean? = null,
    val reasons: List<String>? = null,
    val model_version: String? = null
)

// ----------------- Health DTO -----------------
@Serializable
data class HealthResponse(val ok: Boolean, val service: String, val ts: String)

// ----------------- Ktor module -----------------
fun Application.module() {
    install(StatusPages) {
        exception<Throwable> { call, cause ->
            cause.printStackTrace()
            call.respondText(
                "internal_error: ${cause.message ?: "unknown"}",
                status = io.ktor.http.HttpStatusCode.InternalServerError
            )
        }
    }

    install(ContentNegotiation) {
        // server-side JSON (don’t confuse with client one)
        json(
            ServerJson { ignoreUnknownKeys = true }
        )
    }

    environment.log.info(
        "cfg: pyFraudUrl=${Conf.pyFraudUrl}, " +
                "fraudThreshold=${Conf.fraudThreshold}, " +
                "httpTimeoutMs=${Conf.httpTimeoutMs}, " +
                "mongoDb=${Conf.mongoDb}"
    )

    routing {
        get("/ping")  { call.respondText("pong") }
        get("/")      { call.respond(HealthResponse(true, "kotlin", java.time.Instant.now().toString())) }
        get("/health"){ call.respond(HealthResponse(true, "kotlin", java.time.Instant.now().toString())) }

        // ---- Routes from other files ----
        balanceRoute()

        // Provide a lambda that calls the FastAPI fraud service.
        transferRoute { req ->
            val pyReq = PyFraudReq(
                transaction_id = "precheck",
                account = req.from_account,
                amount = req.amount.toDoubleOrNull() ?: 0.0,
                currency = req.currency,
                metadata = req.metadata
            )
            val response = http.post(Conf.pyFraudUrl) {
                contentType(io.ktor.http.ContentType.Application.Json)
                setBody(pyReq)
            }
            val body: PyFraudResp = response.body()
            (body.fraud_score) to (body.reasons ?: emptyList())
        }
        auditRoutes()
    }
}

fun main() {
    val port = System.getenv("PORT")?.toIntOrNull() ?: 8080
    println(">>> Starting Kotlin service on port $port …")
    embeddedServer(Netty, port = port, module = Application::module).start(wait = true)
}