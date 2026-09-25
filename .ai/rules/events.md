---
paths:
  - 'app/Modules/**/Events/**'
---

# Events

## Team events notify by contract
An event exists only to email a team's Owner and Admins after commit: name it in the past tense, use Dispatchable + SerializesModels, carry models and enums (a name string when the row may be gone), and implement NotifiesTeam (team(), causer(), notification()). Dispatch with Event::dispatch(...). Never add a listener or Event::listen line; the NotifiesTeam interface listener already covers it. Anything else calls the Action or Notification::send directly.
