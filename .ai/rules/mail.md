---
paths:
  - 'app/Mail/**'
---

# Mail

## Don't name Mailable properties `message`
Never declare a Mailable constructor property (or variable) named `message`. Laravel's Mailable base sets `$this->message` to the internal Illuminate\Mail\Message during send, silently overwriting the property and causing `htmlspecialchars(): must be of type string, Illuminate\Mail\Message given` when the view renders `{{ $message }}`. Rename it (e.g. `$enquiry`).
