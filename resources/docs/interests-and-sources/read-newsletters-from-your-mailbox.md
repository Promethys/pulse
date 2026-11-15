You can read newsletters straight from your own mailbox. Pulse watches one folder of a Gmail, iCloud, Yahoo or Fastmail account, or any other mailbox with IMAP, and reads new newsletter subjects, excerpts and links when preparing news.

## Create an app password

Pulse signs in with an app password, separate from your main password. Create one in your mail account's security settings:

- **Gmail**: turn on 2-Step Verification, then create an app password.
- **iCloud**: under Sign-In and Security, create an app-specific password.
- **Yahoo**: under Account security, generate an app password.
- **Fastmail**: under Privacy & Security, create an app password and enable IMAP access.

Another provider works too: look for app passwords in its documentation, then choose **Other** in Pulse.

## Put newsletters in a label

Pulse reads one folder, so keep newsletters apart from the rest of your mail. In Gmail, create a filter that catches your newsletters, for example by sender or by a word they all share, and make it apply a label such as `Newsletters`. With another provider, a dedicated address that only receives newsletters works just as well.

## Connect it to Pulse

1. Open **Sources → My sources → Add a source**.
2. Choose **Email folder**.
3. Pick your **Provider**. Gmail, iCloud, Yahoo and Fastmail fill in their mail server; for another provider, choose **Other** and enter the **Mail server** yourself, for example `imap.example.com`.
4. Enter your **Email or username** and the **App password** you created.
5. Set the **Folder or label** to read, `INBOX` by default. For Gmail, use a label such as `Newsletters`.
6. Enter a **Name** and at least one interest, select **Test now** to see how many recent emails Pulse finds and their first subjects, then **Add** to save.

Pulse connects over a secure connection only, reads the folder you chose, and leaves every email unread: it never moves, flags or deletes anything. Each run reads the emails received since the previous run, newest first, up to 20, and never stores the same email twice. The mail server must be a public server name; Pulse refuses raw addresses and internal names.

## Update or remove a saved folder

Choose **Edit** under **My sources**. Leave the **App password** blank to keep the saved one; it is stored encrypted and never shown again. Change the **Folder or label** to read another folder, or use **Delete** to remove the source. To use **Test now** in this dialog, enter the password again.

> [!NOTE]
> An app password opens your whole mailbox, not only the folder Pulse reads, and you can revoke it anytime in your mail account's settings. A dedicated label, or better a dedicated address, keeps the rest of your mail private. Newsletter excerpts and links can be sent to your AI provider for news selection.
