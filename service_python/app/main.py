from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field
from typing import List, Optional, Set, Literal
import os, json, logging
import uvicorn
from decimal import Decimal
from datetime import datetime

class Settings(BaseModel):
    app_name: str = "Fraud Detection API"
    host: str = os.getenv("HOST", "0.0.0.0")
    port: int = int(os.getenv("PORT", "8001"))
    model_mode: Literal["rules", "ml"] = os.getenv("MODEL_MODE", "rules")
    # NEW: model version advertised by the service
    model_version: str = os.getenv("MODEL_VERSION", "rules-v1")

    amount_threshold: float = float(os.getenv("AMOUNT_THRESHOLD", "10000"))
    high_risk_currencies: Set[str] = set(json.loads(os.getenv("HIGH_RISK_CURRENCIES", '["USD"]')))
    blacklist_accounts: Set[str] = set(json.loads(os.getenv("BLACKLIST_ACCOUNTS", '["ACC000X","ACC999Y"]')))
    weight_blacklist: float = float(os.getenv("WEIGHT_BLACKLIST", "0.7"))
    weight_amount: float = float(os.getenv("WEIGHT_AMOUNT", "0.25"))
    weight_currency: float = float(os.getenv("WEIGHT_CURRENCY", "0.1"))
    decision_threshold: float = float(os.getenv("DECISION_THRESHOLD", "0.8"))

settings = Settings()

app = FastAPI(title=settings.app_name)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("fraud-api")

class Txn(BaseModel):
    transaction_id: str
    account: str
    amount: Decimal = Field(gt=0)
    currency: str
    timestamp: Optional[datetime] = None

class FraudResult(BaseModel):
    transaction_id: str
    fraud_score: float
    is_fraud: bool
    reasons: List[str]

class RulesOut(BaseModel):
    model_mode: str
    model_version: str            # NEW
    amount_threshold: float
    high_risk_currencies: List[str]
    blacklist_accounts: List[str]
    weights: dict
    decision_threshold: float

EPS = 1e-9

def rules_engine(txn: Txn) -> FraudResult:
    reasons: List[str] = []
    score = 0.0

    if txn.account in settings.blacklist_accounts:
        score += settings.weight_blacklist
        reasons.append("Account is blacklisted")

    if txn.amount >= settings.amount_threshold:
        score += settings.weight_amount
        reasons.append(f"Amount exceeds threshold {settings.amount_threshold}")

    if txn.currency in settings.high_risk_currencies:
        score += settings.weight_currency
        reasons.append("High-risk currency")

    score = min(score, 1.0)
    is_fraud = (score + EPS) >= settings.decision_threshold
    return FraudResult(
        transaction_id=txn.transaction_id,
        fraud_score=round(score, 2),
        is_fraud=is_fraud,
        reasons=reasons
    )

def ml_engine(txn: Txn) -> FraudResult:
    # TODO: load model, featurize txn, predict probability
    # For now, call rules so /check_fraud keeps working if MODEL_MODE=ml by mistake
    return rules_engine(txn)

@app.on_event("startup")
def _log_startup():
    logger.info(
        "Starting %s on %s:%s | mode=%s | version=%s",
        settings.app_name, settings.host, settings.port, settings.model_mode, settings.model_version
    )

@app.get("/health")
def health():
    return {"ok": True, "service": settings.app_name, "version": settings.model_version,
            "time": datetime.utcnow().isoformat() + "Z"}

@app.get("/rules", response_model=RulesOut)
def get_rules():
    return RulesOut(
        model_mode=settings.model_mode,
        model_version=settings.model_version,   # NEW
        amount_threshold=settings.amount_threshold,
        high_risk_currencies=sorted(list(settings.high_risk_currencies)),
        blacklist_accounts=sorted(list(settings.blacklist_accounts)),
        weights={
            "blacklist": settings.weight_blacklist,
            "amount": settings.weight_amount,
            "currency": settings.weight_currency
        },
        decision_threshold=settings.decision_threshold
    )

@app.post("/check_fraud", response_model=FraudResult)
def check_fraud(txn: Txn):
    logger.info("check_fraud request: %s", txn.model_dump())
    if settings.model_mode == "ml":
        result = ml_engine(txn)
    else:
        result = rules_engine(txn)
    logger.info("check_fraud response: %s", result.model_dump())
    return result

if __name__ == "__main__":
    uvicorn.run(app, host=settings.host, port=settings.port)