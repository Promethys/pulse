A Discord webhook lets Pulse post without installing the Pulse bot. Pulse sends each digest as a new forum thread, so use a webhook for a forum channel.

![Delivery settings, with Discord webhooks and email](/images/shots/delivery-desktop.webp)

## Add a webhook

1. Create a webhook in Discord for the destination forum channel and copy its URL. You need permission to manage webhooks there.
2. Open **Settings → Delivery → Discord webhooks → Add webhook**.
3. Enter a **Name** and **Webhook URL**.
4. Under **Send to this destination**, select **News**, **Learning** or both.
5. Select **Add**, then use **Test** on the saved destination.

> [!WARNING]
> A webhook URL is a secret that allows posting to its channel. Keep it private and replace it in Discord if it is exposed.

## Change or pause it

Saved destination changes apply automatically. Text fields save when you leave the field. Use **Replace webhook URL** to enter a new URL; leaving it blank keeps the current one.

Turn off the destination's switch to pause it, or use **Delete** to remove it. You must keep at least one digest kind selected.

You can have up to 20 Discord destinations, counting bot and webhook destinations together. **Test** sends a real Discord post. Delivery tests allow six per minute and 20 per hour per account.

## If posting fails

Check that the webhook still exists and belongs to the intended forum. A missing webhook can cause Pulse to disable the destination. Replace its URL, turn it back on and test again.

For the bot option, see [Add Pulse to Discord](/docs/delivery/add-pulse-to-discord).
