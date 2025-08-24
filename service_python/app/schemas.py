from datetime import datetime
from decimal import Decimal
from typing import Optional

from pydantic import BaseModel, Field, IPvAnyAddress

# NOTE: "from" and "to" are JSON keys; we map them to Python-safe names via alias.
class ScoreRequest(BaseModel):
    from_account: str = Field(..., alias="from", min_length=1, max_length=64)
    to_account: str = Field(..., alias="to", min_length=1, max_length=64)
    amount: Decimal = Field(..., description="Transaction amount, 2-decimal money")
    device: Optional[str] = Field(default=None, max_length=64)
    ip: Optional[IPvAnyAddress] = None
    ts: datetime

    model_config = {
        "populate_by_name": True,
        "str_strip_whitespace": True,
    }

class ScoreResponse(BaseModel):
    risk: float = Field(ge=0.0, le=1.0)
    rule: str
