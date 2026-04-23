#!/usr/bin/env python3

import argparse
import json
import sys


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


def load_pipeline(openvino_genai, model_path, pipeline_type):
    pipeline_type = (pipeline_type or "vlm").strip().lower()

    if pipeline_type == "llm":
        return openvino_genai.LLMPipeline(model_path, "CPU")

    return openvino_genai.VLMPipeline(model_path, "CPU")


def run_non_stream(openvino_genai, model_path, pipeline_type, prompt, max_new_tokens):
    pipeline = load_pipeline(openvino_genai, model_path, pipeline_type)
    result = pipeline.generate(prompt, max_new_tokens=max_new_tokens)

    if not isinstance(result, str) or result.strip() == "":
        raise RuntimeError("OpenVINO n a retourne aucun rapport exploitable.")

    sys.stdout.write(result)
    sys.stdout.flush()
    return 0


def run_stream(openvino_genai, model_path, pipeline_type, prompt, max_new_tokens):
    pipeline = load_pipeline(openvino_genai, model_path, pipeline_type)

    def streamer(subword):
        if subword:
            emit_stream_event("token", content=subword)
        return openvino_genai.StreamingStatus.RUNNING

    pipeline.generate(prompt, max_new_tokens=max_new_tokens, streamer=streamer)
    emit_stream_event("done")
    return 0


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--stream", action="store_true")
    args = parser.parse_args()

    try:
        import openvino_genai as ov_genai
    except ImportError:
        return fail("Le package openvino_genai n est pas installe dans l environnement Python.", args.stream)

    try:
        config = json.load(sys.stdin)
    except json.JSONDecodeError:
        return fail("Configuration JSON invalide transmise au script Python OpenVINO.", args.stream)

    if not isinstance(config, dict):
        return fail("Configuration Python OpenVINO invalide.", args.stream)

    model_path = config.get("model_path")
    pipeline_type = config.get("pipeline", "vlm")
    payload = config.get("payload")

    if not isinstance(model_path, str) or not model_path.strip():
        return fail("OPENVINO_MODEL_PATH est invalide ou manquant.", args.stream)

    if not isinstance(payload, dict):
        return fail("Le payload OpenVINO est invalide.", args.stream)

    prompt = payload.get("prompt")
    max_new_tokens = payload.get("max_new_tokens", 900)

    if not isinstance(prompt, str) or not prompt.strip():
        return fail("Le prompt OpenVINO est invalide.", args.stream)

    if not isinstance(max_new_tokens, int) or max_new_tokens <= 0:
        max_new_tokens = 900

    try:
        if args.stream:
            return run_stream(ov_genai, model_path, pipeline_type, prompt, max_new_tokens)
        return run_non_stream(ov_genai, model_path, pipeline_type, prompt, max_new_tokens)
    except RuntimeError as error:
        return fail(str(error), args.stream)
    except Exception:
        return fail("Erreur inattendue pendant la generation OpenVINO du rapport.", args.stream)


if __name__ == "__main__":
    raise SystemExit(main())
