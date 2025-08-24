plugins {
    kotlin("jvm") version "1.9.24"
    kotlin("plugin.serialization") version "1.9.24"
    application
}

repositories { mavenCentral() }

val ktorVersion = "2.3.12"
val serializationVersion = "1.6.3"

dependencies {
    implementation("io.ktor:ktor-server-core-jvm:$ktorVersion")
    implementation("io.ktor:ktor-server-netty-jvm:$ktorVersion")
    implementation("io.ktor:ktor-server-content-negotiation-jvm:$ktorVersion")
    implementation("io.ktor:ktor-server-status-pages-jvm:$ktorVersion")

    implementation("io.ktor:ktor-serialization-kotlinx-json-jvm:$ktorVersion")
    implementation("org.jetbrains.kotlinx:kotlinx-serialization-json:$serializationVersion")
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-core:1.8.1")

    implementation("io.ktor:ktor-client-core:$ktorVersion")
    implementation("io.ktor:ktor-client-cio:$ktorVersion")
    implementation("io.ktor:ktor-client-content-negotiation:$ktorVersion")
    implementation("io.ktor:ktor-serialization-kotlinx-json:$ktorVersion")

    implementation("org.mongodb:mongodb-driver-kotlin-coroutine:5.1.0")
    implementation("org.mongodb:bson-kotlinx:5.1.0")
    implementation("org.jetbrains.kotlinx:kotlinx-serialization-core:1.6.3")
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-core:1.8.1")
}

application {
    mainClass.set("com.fds.ApplicationKt")
}

tasks.named<JavaExec>("run") {
    environment("MONGODB_URI", System.getenv("MONGODB_URI")
        ?: "mongodb+srv://ritikpathania:06kzcC9gnWeCghvr@cluster0.fmmyiii.mongodb.net/?retryWrites=true&w=majority")
    environment("MONGODB_DB",  System.getenv("MONGODB_DB") ?: "bank")
    environment("PY_FRAUD_URL", System.getenv("PY_FRAUD_URL") ?: "http://127.0.0.1:8001")
    environment("FRAUD_THRESHOLD", System.getenv("FRAUD_THRESHOLD") ?: "0.8")
    environment("HTTP_TIMEOUT_MS", System.getenv("HTTP_TIMEOUT_MS") ?: "2000")
}