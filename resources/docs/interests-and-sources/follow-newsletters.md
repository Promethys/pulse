You can connect a Resend receiving inbox as a personal news source. Pulse reads received newsletter subjects, excerpts and links when preparing news. To read newsletters from a mailbox you already have, see [Read newsletters from your mailbox](/docs/interests-and-sources/read-newsletters-from-your-mailbox) instead.

## Prepare your inbox

Set up email receiving in your own Resend account, then subscribe newsletters to that receiving address or arrange forwarding to it. Pulse does not create an inbox address for you.

## Connect it to Pulse

1. Open **Sources → My sources → Add a source**.
2. Choose **Resend inbox**.
3. Enter your **Resend API key**, a **Name** and at least one interest.
4. Optionally enter a **Receiving address** to keep only emails sent to that address. Leave it empty to read the whole inbox.
5. Select **Test now**, then **Add** to save.

The key needs **Full access**: Resend refuses to list received emails with a **Sending access** key. Pulse uses the key saved on this source to read the receiving inbox. Its credentials are stored encrypted.

Without a receiving address, Pulse reads every email your Resend account receives, not only your newsletters. Use a Resend account, or at least a receiving address, that only receives newsletters.

## Forward newsletters from Gmail

1. In Gmail, create a filter that catches your newsletters, for example by sender or by a word they all share.
2. Choose **Forward it to** and enter your Resend receiving address. Gmail first asks you to confirm the forwarding address by sending a code to it. That confirmation email arrives in your Resend account, where you can read the code.
3. Enter the same address in the **Receiving address** field of your Pulse source, so Pulse reads only the forwarded newsletters.

A forwarded newsletter still shows your Gmail address in its To line. Pulse also checks the address an email was delivered to, so forwarded newsletters are kept.

## Update a saved inbox

Choose **Edit** under **My sources**. Leave the key blank to keep the saved key, or enter a replacement. The **Receiving address** is prefilled: change it, or clear it to read the whole inbox again. To use **Test now** in this dialog, enter the key again.

> [!NOTE]
> Newsletter excerpts and links can be sent to your AI provider for news selection. Connect an inbox intended for the newsletters you want Pulse to read.
