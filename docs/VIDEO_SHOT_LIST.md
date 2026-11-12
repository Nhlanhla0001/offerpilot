# Exact recording shot list

1. Set browser zoom so app text is readable, close notification popups, and remove saved-password suggestions. Use a demo database and a separate PayPal Sandbox personal buyer. Never show API keys, PayPal passwords, private minimum prices, or real customer data.
2. **00:00–00:20:** Open `/` and show OfferPilot's headline and demo-shop entry. Begin narration.
3. **00:20–00:40:** Open `/login`, use the seeded demo seller account off-camera, then navigate to `/dashboard` and `/inventory`. Show the product list and public prices. Avoid opening `/inventory/{product}/edit`, where private minimums appear.
4. **00:40–01:30:** Sign out or use a separate guest browser window. Navigate to `/store`, then `/store/HEADPHONES-PRO`. Ask one real product question in the assistant. Use the visible maximum-confirmation control, make an offer, and wait for the actual AI response. Accept a quote if offered and confirm the cart action. If an offer is rejected, show that result honestly and use an allowed offer for the checkout sequence.
5. **01:30–02:15:** Open `/cart`, review the item and amount, go to `/checkout` or the product's checkout control, then use the actual PayPal Sandbox button. Enter sandbox buyer credentials with recording paused or the PayPal popup obscured. Resume at the PayPal approval summary and continue through the real capture confirmation.
6. **02:15–02:35:** Return to the seller browser session at `/orders`, refresh, and show the new paid order and capture information. Keep private customer details out of frame.
7. **02:35–02:45:** Return to `/` or `/store` for the closing sentence.
8. Export as MP4 under 3:00. Check the whole export for visible secrets and verify the payment shown matches the order. Upload publicly to YouTube and paste the URL into Devpost.

FFmpeg is available on the development machine, but no ready Playwright or OBS recorder was found in the project. Use Windows Snipping Tool screen recording (Win+Shift+R) or another existing screen recorder, then trim/export to MP4. Do not install tooling at the expense of the submission deadline.
