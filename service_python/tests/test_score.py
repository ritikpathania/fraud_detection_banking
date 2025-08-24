from fastapi.testclient import TestClient
from app.main import app

client = TestClient(app)

def test_high_amount_is_flagged():
    body = {
        "from": "ACC-001",
        "to": "ACC-999",
        "amount": "10000.00",
        "device": "web",
        "ip": "1.2.3.4",
        "ts": "2025-08-22T01:00:00Z"
    }
    r = client.post("/score", json=body)
    assert r.status_code == 200
    data = r.json()
    assert data["risk"] == 0.9
    assert data["rule"] == "AMOUNT_GE_10000"

def test_low_amount_is_baseline():
    body = {
        "from": "ACC-001",
        "to": "ACC-999",
        "amount": "9999.99",
        "ts": "2025-08-22T01:00:00Z"
    }
    r = client.post("/score", json=body)
    assert r.status_code == 200
    data = r.json()
    assert 0.0 <= data["risk"] < 0.9
    assert data["rule"] == "BASELINE"
