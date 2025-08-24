package com.fds.db

import com.mongodb.ConnectionString
import com.mongodb.MongoClientSettings
import com.mongodb.kotlin.client.coroutine.MongoClient
import com.mongodb.kotlin.client.coroutine.MongoDatabase
import com.mongodb.kotlin.client.coroutine.MongoCollection
import com.fds.domain.*
import com.fds.config.Conf
import org.bson.codecs.configuration.CodecRegistries.fromProviders
import org.bson.codecs.configuration.CodecRegistries.fromRegistries
import org.bson.codecs.kotlinx.KotlinSerializerCodecProvider

object Mongo {
    private val uri = Conf.mongoUri
    private val dbName = Conf.mongoDb

    // Use Kotlinx serialization for @Serializable data classes
    private val codecRegistry = fromRegistries(
        MongoClientSettings.getDefaultCodecRegistry(),
        fromProviders(KotlinSerializerCodecProvider())
    )

    private val settings = MongoClientSettings.builder()
        .applyConnectionString(ConnectionString(uri))
        .codecRegistry(codecRegistry)
        .build()

    val client: MongoClient = MongoClient.create(settings)
    val db: MongoDatabase = client.getDatabase(dbName)

    val accounts:     MongoCollection<BankAccount> = db.getCollection("accounts")
    val transactions: MongoCollection<Txn>         = db.getCollection("transactions")
    val idempotency:  MongoCollection<Idempotency> = db.getCollection("idempotency")
    val fraudAudit:   MongoCollection<FraudAudit>  = db.getCollection("fraud_audit")
}