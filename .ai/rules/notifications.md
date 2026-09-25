---
paths:
  - 'app/Modules/**/Notifications/**'
---

# Notifications

## Team emails extend TeamNotification
An email Owners and Admins can switch off extends TeamNotification: declare its NotificationType, take Team $team first and call parent::__construct($team), and write subject, lines and action in compose(BrandedMailMessage, User) with trans(). Non-switchable emails extend Notification implements ShouldQueue but still return a BrandedMailMessage. Carry names and enums, not Users that may be deleted. Never add Mailables; use ->markdown() on the message for tabular bodies.
