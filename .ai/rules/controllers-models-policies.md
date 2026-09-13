---
paths:
  - 'app/{Http/Controllers,Models,Policies}/**/*.php'
---

# Controllers Models Policies

## Trip lifecycle is system managed
Trips start as draft. The first approved customer payment moves draft to confirmed. A TL assignment starts pending and only that TL may accept or reject it; acceptance is allowed only for a confirmed trip and moves it to ongoing. TL finance submissions require an accepted assignment and an ongoing trip. Reassignment or removal from an ongoing trip returns it to confirmed.
