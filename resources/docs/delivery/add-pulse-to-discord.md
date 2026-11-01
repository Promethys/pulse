## Before you start

Pulse can publish news and learning digests as posts in a Discord forum channel. You need permission to add apps to your server and a forum channel the bot can see and write to.

If Discord connections are not available on your Pulse instance, or you cannot add the bot, use a [Discord webhook](/docs/delivery/discord-webhooks) instead.

## Add the Pulse bot

1. In Pulse, open **Settings → Delivery**.
2. Under **Discord servers**, choose **Add to Discord**.
3. In Discord, select your server and review the permissions before authorizing the bot.
4. Discord returns you to Pulse. Your server now appears under **Discord servers**.

> [!NOTE]
> Adding the server does not choose a destination. Add a forum channel next. You can have up to 20 Discord destinations, including bot channels and webhooks.

## Choose a forum channel

1. Under the server, choose **Add a forum channel**.
2. Choose a **Forum channel** from the list.
3. Choose which digests it receives and save the destination.

> [!WARNING]
> Forum channels only: the bot creates forum posts, so regular text channels are not supported by the channel picker. If no forums appear, enable Community in your Discord server settings, create a Forum channel, and reload Pulse. Make sure the bot can access that channel.

### Pick what arrives

Each destination has its own news and learning selection. News is your curated news digest. Learning contains the developer tip and topic of the day.

| Destination                   | News | Learning |
| ----------------------------- | ---- | -------- |
| Bot in a forum channel        | Yes  | Yes      |
| Discord webhook               | Yes  | Yes      |
| Bot in a regular text channel | No   | No       |

You can change the forum channel or the selected digests later in Settings → Delivery. Disable a destination to pause it, or delete it to remove it from Pulse.

## Test it

Use **Test** on the destination to send a test message without waiting for the next scheduled digest. If it fails, check that the bot is in the server and has access to the selected forum channel.

A Discord error such as this means the bot cannot access the destination:

```text
403 Missing Access
```

Review the channel's permissions and the bot's server membership, then test again. Disconnecting a server in Pulse removes its destinations from Pulse but leaves the bot in Discord.

> [!TIP]
> Give the digest its own forum channel, such as `daily-reading`, so the daily posts are easy to find.
