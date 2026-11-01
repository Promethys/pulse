You can save up to five AI keys, with one key per provider. Their order decides which provider Pulse tries first.

![AI key settings, with two keys in fallback order](/images/shots/ai-keys-desktop.webp)

## Add or replace a key

1. Open **Settings → AI keys → Add a key**.
2. Choose **Provider** and **Model**. For a model outside the suggestions, choose **Other model** and enter the provider's exact model ID.
3. Enter the API key and use **Test the key** if you want to check it.
4. Select the consent checkbox, then **Add**.

Choosing a provider you already saved changes the action to **Replace key**. It replaces that provider's key and model while keeping its position.

Testing does not save the key. It sends a real request to the provider, which may charge your account.

## Set the fallback order

Use **Move up** and **Move down** beside saved keys. The first key is marked **Default**. Order changes save immediately.

Pulse tries the next provider for rate limits, overload, insufficient credits and connection failures. Other errors can stop generation without trying the remaining keys.

## Test or delete saved keys

Use **Test** beside a saved key. AI key tests allow six requests per minute.

Choose **Delete** and confirm to remove a key. Removing your last key pauses scheduled digests unless your account has instance AI access. Personal keys never fall back to instance keys while any personal key remains.
