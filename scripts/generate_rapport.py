#!/usr/bin/env python3

import argparse
import json
import sys
import urllib.error
import urllib.request


def extract_full_text(response_payload):
    choice = response_payload.get("choices", [{}])[0]
    if not isinstance(choice, dict):
        return ""

    message = choice.get("message", {}).get("content")
    if isinstance(message, str):
        return message
    if isinstance(message, list):
        return flatten_content_parts(message)

    text = choice.get("text")
    return text if isinstance(text, str) else ""


def extract_stream_token(response_payload):
    choice = response_payload.get("choices", [{}])[0]
    if not isinstance(choice, dict):
        return ""

    delta = choice.get("delta", {}).get("content")
    if isinstance(delta, str):
        return delta
    if isinstance(delta, list):
        return flatten_content_parts(delta)

    message = choice.get("message", {}).get("content")
    if isinstance(message, str):
        return message
    if isinstance(message, list):
        return flatten_content_parts(message)

    return ""


def flatten_content_parts(parts):
    segments = []
    for part in parts:
        if isinstance(part, str):
            segments.append(part)
            continue
        if not isinstance(part, dict):
            continue
        text = part.get("text") or part.get("content")
        if isinstance(text, str) and text:
            segments.append(text)
    return "".join(segments)


def extract_error_message(raw_body):
    try:
        payload = json.loads(raw_body)
    except json.JSONDecodeError:
        return raw_body.strip() or None

    if not isinstance(payload, dict):
        return raw_body.strip() or None

    error = payload.get("error")
    if isinstance(error, dict):
        message = error.get("message")
        if isinstance(message, str) and message.strip():
            return message.strip()

    message = payload.get("message")
    if isinstance(message, str) and message.strip():
        return message.strip()

    return raw_body.strip() or None


def humanize_transport_error(error):
    message = str(getattr(error, "reason", error)).strip().lower()

    if "connection refused" in message or "failed to establish a new connection" in message:
        return "LM Studio est inaccessible. Verifiez que le serveur local est demarre sur http://127.0.0.1:1234."

    if "timed out" in message or "timeout" in message:
        return "LM Studio a mis trop de temps a repondre. Reessayez dans un instant."

    return "Impossible de contacter LM Studio pour generer le rapport."


def emit_stream_event(event_type, **payload):
    message = {"type": event_type, **payload}
    sys.stdout.write(json.dumps(message, ensure_ascii=False) + "\n")
    sys.stdout.flush()


def fail(message, stream_mode):
    if stream_mode:
        emit_stream_event("error", message=message)
    else:
        sys.stderr.write(message + "\n")
        sys.stderr.flush()
    return 1


def request_lm_studio(url, payload):
    endpoint = url.rstrip("/") + "/v1/chat/completions"
    body = json.dumps(payload).encode("utf-8")
    request = urllib.request.Request(
        endpoint,
        data=body,
        headers={
            "Content-Type": "application/json",
            "Accept": "application/json",
        },
        method="POST",
    )

    return urllib.request.urlopen(request, timeout=240)


def run_non_stream(url, payload):
    with request_lm_studio(url, payload) as response:
        body = response.read().decode("utf-8")

    try:
        response_payload = json.loads(body)
    except json.JSONDecodeError as error:
        raise RuntimeError("LM Studio a retourne une reponse JSON invalide.") from error

    report = extract_full_text(response_payload).strip()
    if not report:
        raise RuntimeError("Le modele local n'a retourne aucun rapport exploitable.")

    sys.stdout.write(report)
    sys.stdout.flush()
    return 0


def run_stream(url, payload):
    with request_lm_studio(url, payload) as response:
        for raw_line in response:
            line = raw_line.decode("utf-8", errors="replace").strip()
            if not line or not line.startswith("data:"):
                continue

            data = line[5:].strip()
            if not data:
                continue

            if data == "[DONE]":
                emit_stream_event("done")
                return 0

            try:
                decoded = json.loads(data)
            except json.JSONDecodeError:
                continue

            token = extract_stream_token(decoded)
            if token:
                emit_stream_event("token", content=token)

    emit_stream_event("error", message="Le flux LM Studio s est termine sans marqueur de fin.")
    return 1


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--stream", action="store_true")
    args = parser.parse_args()

    try:
        config = json.load(sys.stdin)
    except json.JSONDecodeError:
        return fail("Configuration JSON invalide transmise au script Python.", args.stream)

    if not isinstance(config, dict):
        return fail("Configuration Python invalide.", args.stream)

    url = config.get("lm_studio_url")
    payload = config.get("payload")

    if not isinstance(url, str) or not url.strip():
        return fail("LM_STUDIO_URL est invalide.", args.stream)

    if not isinstance(payload, dict):
        return fail("Le payload LM Studio est invalide.", args.stream)

    try:
        if args.stream:
            return run_stream(url, payload)
        return run_non_stream(url, payload)
    except urllib.error.HTTPError as error:
        raw_body = error.read().decode("utf-8", errors="replace")
        message = extract_error_message(raw_body) or f"LM Studio a retourne une erreur HTTP {error.code}."
        return fail(message, args.stream)
    except urllib.error.URLError as error:
        return fail(humanize_transport_error(error), args.stream)
    except RuntimeError as error:
        return fail(str(error), args.stream)
    except Exception:
        return fail("Erreur inattendue pendant la generation locale du rapport.", args.stream)


if __name__ == "__main__":
    raise SystemExit(main())
