from fastapi.testclient import TestClient
from unittest.mock import patch, MagicMock
from app.main import app

# Test data
low_risk_transaction = {"account": "acc_123", "amount": 150.75, "currency": "USD"}
high_risk_transaction = {"account": "acc_456", "amount": 25000.00, "currency": "EUR"}

@patch('app.main.preprocessor')
@patch('app.main.model')
def test_low_risk_transaction(mock_model, mock_preprocessor):
    """Tests that a low-risk transaction is correctly identified as not fraudulent."""
    mock_model.predict_proba.return_value = [[0.9, 0.1]]
    # CORRECTED: Return a list of lists (or a numpy array) to mimic the real preprocessor
    mock_preprocessor.transform.return_value = [[1, 2, 3, 4]]

    client = TestClient(app)
    response = client.post("/score", json=low_risk_transaction)
    data = response.json()

    assert response.status_code == 200
    assert data["is_fraud"] is False
    assert data["fraud_score"] == 0.1

@patch('app.main.preprocessor')
@patch('app.main.model')
def test_high_risk_transaction(mock_model, mock_preprocessor):
    """Tests that a high-risk transaction is correctly flagged as fraudulent."""
    mock_model.predict_proba.return_value = [[0.1, 0.99]]
    # CORRECTED: Return a list of lists (or a numpy array)
    mock_preprocessor.transform.return_value = [[1, 2, 3, 4]]

    client = TestClient(app)
    response = client.post("/score", json=high_risk_transaction)
    data = response.json()

    assert response.status_code == 200
    assert data["is_fraud"] is True
    assert data["fraud_score"] == 0.99

def test_rules_endpoint():
    """Tests the /rules endpoint."""
    client = TestClient(app)
    response = client.get("/rules")
    data = response.json()

    assert response.status_code == 200
    assert "prediction_threshold" in data