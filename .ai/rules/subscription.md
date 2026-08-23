---
paths:
  - 'app/Services/Landlord/Subscription/**'
---

# Subscription

## Subscription expiring/expired lifecycle job
Daily ProcessSubscriptionLifecycleJob marks Active/Trialing/PastDue subscriptions expired when accessEndsAt() has passed and fires SubscriptionExpired. Sends SubscriptionExpiring once per period end within notifications.subscription.expiring_days (SUBSCRIPTION_EXPIRING_DAYS). Dedup via metadata.expiring_notified_period_end.
