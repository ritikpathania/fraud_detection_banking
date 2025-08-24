@component('mail::message')
# Fraudulent Transaction Detected 🚨

A suspicious transaction has been flagged:

- **Transaction ID:** {{ $txn->transaction_id }}
- **Amount:** {{ $txn->amount }} {{ $txn->currency }}
- **Fraud Score:** {{ $txn->fraud_score }}
- **Reasons:**
@foreach ($txn->reasons as $reason)
    - {{ $reason }}
@endforeach

Please review immediately.

Thanks,
Fraud Detection System
@endcomponent
