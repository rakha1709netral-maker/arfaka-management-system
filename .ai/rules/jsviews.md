---
paths:
  - 'app/Http/Requests/**,resources/{js,views}/**/*'
---

# Jsviews

## Normalize mobile camera receipts
TL receipt inputs must accept image/* because Android and iPhone cameras expose different source formats. Client code normalizes browser-readable photos to compressed JPEG with timestamp and GPS; backend validates a safe image and 2 MB final size instead of requiring only jpg/jpeg extensions. Finance printing must keep a navigable print-mode fallback because window.print may not open directly in mobile browsers or installed web apps.
