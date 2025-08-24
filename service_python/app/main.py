from fastapi import FastAPI
from pydantic import BaseModel
import pandas as pd
import joblib
from datetime import datetime
from typing import List

# --- Pydantic Models ---
class Transaction(BaseModel):
    account: str
    amount: float
    currency: str

class FraudResponse(BaseModel):
    is_fraud: bool
    fraud_score: float
    reasons: List[str]

# --- FastAPI Application ---
app = FastAPI(title="Fraud Detection API")

# --- Load Models ---
model = joblib.load('app/xgboost_model.pkl')
preprocessor = joblib.load('app/preprocessor.pkl')

# --- Optimal Threshold ---
OPTIMAL_THRESHOLD = 0.9462

@app.post("/score", response_model=FraudResponse)
def score_transaction(transaction: Transaction):
    """
    Scores a transaction using the final tuned XGBoost model and optimal threshold.
    """
    input_df = pd.DataFrame([transaction.model_dump()])

    # --- Feature Engineering (with placeholders) ---
    now = datetime.now()
    input_df['hour_of_day'] = now.hour
    input_df['day_of_week'] = now.weekday()
    input_df['avg_amount_per_account'] = input_df['amount']
    input_df['amount_deviation'] = 0
    input_df['time_since_last_txn'] = 1000

    preprocessed_input = preprocessor.transform(input_df)
    # THE FIX IS HERE: Use correct Python list indexing
    fraud_probability = model.predict_proba(preprocessed_input)[0][1]
    is_fraud = fraud_probability > OPTIMAL_THRESHOLD

    reasons = ["High fraud score detected by tuned ML model"] if is_fraud else []

    return FraudResponse(
        is_fraud=bool(is_fraud),
        fraud_score=float(fraud_probability),
        reasons=reasons
    )

@app.get("/rules")
def get_rules():
    """
    Endpoint to show that fraud detection is now model-driven.
    """
    return {
        "message": "Fraud detection is handled by a tuned ML model.",
        "prediction_threshold": OPTIMAL_THRESHOLD
    }