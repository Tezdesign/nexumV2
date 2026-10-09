# Chat

## Overview

Direct messages and group conversations with text, file and GIF messages, read receipts, link previews, an AI summary of unread messages, and voice or video calls. The messages live in this app's database. The calls and their real time signalling run on an external server that is not in this repository.

## Key files

| File | Owns |
|---|---|
| `ConversationController.php` | The `/apps-chat` page, DM and group creation, group rename, avatar and members, leave or delete, and all call routes (invite, accept, reject, token, LiveKit proxies, call page) |
| `MessageController.php` | List messages (with read receipts), send, edit, delete, link preview, AI summary |
| `MessageAttachmentController.php` | File upload (30 MB), GIF import from a URL, attachment listing and streaming with Range support |
| `../../Entity/Chat/` | `Conversation` (type `DM` or `GROUP`, `dm_key` such as `3_8`), `ConversationParticipant` (role `owner` or `member`, `left_at`, `last_read_message_id`), `Message` (kind `TEXT`, `ATTACHMENT` or `CALL`, body up to 300 characters), `MessageAttachment` (file bytes in a LONGBLOB) |
| `../../Repository/Chat/` | Participant checks (`isActiveParticipant`), DM lookup, unread counts |
| `../../Service/Chat/ConversationSidebarProvider.php` | The conversation list with last message and unread counts |
| `../../../templates/chat/apps-chat.html.twig`, `call.html.twig` | The page and the call popup |
| `../../../public/js/pages/apps-chat.js` | All the client logic (5,600 lines, plain JS, no build step). It uses SockJS and STOMP from `public/js/vendor/` |

## Conventions

- Routes are JSON endpoints under `/apps-chat/...`. Every message route first checks `ConversationParticipantRepository::isActiveParticipant($conversationId, $userId)` and returns 403 otherwise. Copy that check in any new route.
- All three chat controllers carry `#[RequireLogin]`, so visitors never reach a chat route (a visitor was user id 0 and could create DMs). Message routes still check `isActiveParticipant($conversationId, $userId)` and return 403 otherwise. Copy both in any new route.
- A call invite is stored as a `CALL` message with body `AUDIO|conv-<id>` or `VIDEO|conv-<id>`. The room name is always `conv-<conversationId>`.
- Call tokens: `proxyLivekitToken` and `getCallToken` share `fetchCallToken()`. The room is built from the conversation id (`conv-<id>`), the caller must be a participant, and the identity (`user-<id>`) and name come from the session, never from the browser.
- Group admin rights belong to the participant with role `owner` (and the creator). The rules (rename, kick, nickname) live on the `Conversation` entity methods, not in the controller.
- "Remove conversation" on a DM only hides it for the person who asked: their participant row gets `left_at`, they lose access and the sidebar skips it, while the other person keeps everything. A new message in the DM, or starting that DM again, clears `left_at`. Groups still delete their rows (a member leaves, the owner deletes the group and its messages).
- Messages come from `findByConversationOrdered`, so the whole history is returned on each open (no pagination yet).
- User pictures are never embedded in JSON. `UserAvatarUrl::for($user)` gives `/apps-chat/users/<id>/avatar` (or null so the page shows initials), and `ConversationController::userAvatar()` serves it with an ETag. It checks the bytes and serves only JPEG, PNG, GIF or WebP, because the profile upload does not check the file. Group avatars are still data URIs (one per conversation).
- A DM's `dm_key` is `<smaller id>_<larger id>`. Use `ConversationRepository::findDmPartnersForUser()` (one query, returns conversation id => other user id), never load all DMs or call `findExistingDM()` in a loop.

## Gotchas

- Real time is mostly missing here. The page does not poll for new messages, so a new message from someone else appears only when the conversation is opened again.
- `ConversationController::sendCallSignal()` only writes to the PHP error log, and `/apps-chat/calls/poll` always returns an empty list. Call ringing works only through the external STOMP server at `CHAT_CALL_SOCKET_URL` (`ws://<UNIVERSEL_WB_URL>:8090/ws`, topic `/topic/call.<id>`, destinations `/app/call.start`, `/app/call.accept`, `/app/call.reject`). LiveKit is at `CHAT_CALL_LIVEKIT_URL` (port 7880), tokens come from `CHAT_CALL_TOKEN_PROXY_TARGET`. If that machine is off, calls fail while chat itself still works.
- Defaults to `10.102.88.72` are still hardcoded in `ConversationController` (token proxy, avatar proxy, call URL) next to the `.env` settings. Prefer the env values.
- The JS posts to `/apps-chat/conversations/<id>/messages/<id>/read`, which has no route.
- The AI summary calls LM Studio with the messages the browser sends (not the ones in the database). It needs `LM_STUDIO_URL` and the model `dolphin3.0-llama3.1-8b`. The local AI engine rebuild may replace it.
- GIFs use the Klipy API (`KLIPY_API_KEY`, `KLIPY_BASE_URL`, `KLIPY_LOCALE`, also read under the older `KILPY_*` names). The server downloads the GIF from the URL the browser gives, through `PublicUrlFetcher`, keeps it only if the bytes really are a GIF, PNG, JPEG or WebP, and refuses anything over 15 MB.
- Any URL fetched on a user's behalf (link preview, GIF import) must go through `App\Service\Chat\PublicUrlFetcher`. It wraps Symfony's `NoPrivateNetworkHttpClient`, which refuses private and loopback addresses even when a host name resolves to one or a redirect points to one, and it stops reading at a byte limit. Never call `file_get_contents($url)` or the plain HttpClient with a user URL.
- Attachments are served inline only when `isInlineMediaMimeType()` allows the type (plain pictures, audio, video). SVG, HTML and everything else is a download, and every attachment response carries `nosniff` and a sandboxing `Content-Security-Policy`.
- Attachments are stored inside the database, so large uploads grow the `message_attachments` table fast.
- A file or GIF message and its attachment are saved together by `saveAttachmentMessage()` in one transaction, and `upload()` checks every file before saving any, so a bad file never leaves an empty message or keeps the files before it.
- There are no tests for this module.

_Drafted by /audit from the repo, worth a quick human pass. Edit freely: once a line stops matching this draft, later runs treat it as curated and will flag rather than overwrite it._
