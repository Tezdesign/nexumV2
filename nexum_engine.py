import json
import logging
from transformers import AutoProcessor
from optimum.intel.openvino import OVModelForVisualCausalLM

# Suppress background warnings
logging.getLogger("transformers").setLevel(logging.ERROR)

MODEL_PATH = "C:/models/gemma-4-ov"
DEVICE = "GPU"

print(f"--- Booting Nexum Engine Core on {DEVICE} ---")
try:
    model = OVModelForVisualCausalLM.from_pretrained(MODEL_PATH, device=DEVICE)
    processor = AutoProcessor.from_pretrained(MODEL_PATH)

    # --- DYNAMIC IDENTITY EXTRACTION (RESTORED) ---
    text_config = getattr(model.config, "text_config", model.config)
    CONTEXT_WINDOW = getattr(text_config, "max_position_embeddings", "Unknown")
    MODEL_TYPE = getattr(text_config, "model_type", "Gemma").capitalize()

    print(f"--- Engine Online: {MODEL_TYPE} ({CONTEXT_WINDOW} max tokens) ---")
except Exception as e:
    print(f"CRITICAL ERROR: Failed to load model. {e}")
    exit(1)

def _run_inference(system_instruction, user_prompt, max_tokens, temp=0.1):
    """Internal helper to handle the actual OpenVINO generation and JSON cleanup."""
    chat_history = [
        {"role": "system", "content": [{"type": "text", "text": system_instruction}]},
        {"role": "user", "content": [{"type": "text", "text": user_prompt}]}
    ]

    try:
        inputs = processor.apply_chat_template(
            chat_history, tokenize=True, return_dict=True, return_tensors="pt", add_generation_prompt=True
        )

        output = model.generate(
            **inputs,
            max_new_tokens=max_tokens,
            do_sample=True,
            temperature=temp, # Dynamic temperature based on the task
            top_p=0.9
        )

        input_length = inputs["input_ids"].shape[1]
        response_text = processor.decode(output[0, input_length:], skip_special_tokens=True).strip()

        # Clean up Markdown JSON artifacts
        if response_text.startswith("```json"):
            response_text = response_text.replace("```json", "", 1)
        if response_text.endswith("```"):
            response_text = response_text[::-1].replace("```", "", 1)[::-1]

        result_json = json.loads(response_text.strip())
        result_json['status'] = 'SUCCESS'
        return result_json

    except json.JSONDecodeError:
        return {"status": "ERROR", "message": "AI did not return valid JSON", "raw_output": response_text}
    except Exception as e:
        return {"status": "ERROR", "message": str(e)}

# --- TASK 1: Parse Draft Intent (STRICT - Temp 0.1) ---
def extract_draft_intent(data):
    system_instruction = (
        f"You are Nexum, a data-extraction AI based on the {MODEL_TYPE} architecture "
        f"running locally via 8-bit OpenVINO with a {CONTEXT_WINDOW}-token context window. "
        "You must output ONLY valid JSON format. Do not include conversational text or markdown."
    )
    user_prompt = f"""Convert the user's natural language request into a structured JSON expense draft.
User Request: "{data.get('user_input', '')}"
Available Projects: {json.dumps(data.get('available_projects', []))}
Available Categories: {json.dumps(data.get('available_categories', []))}

Provide a JSON response with the following keys exactly:
- "amount": numeric value. Extract from text.
- "project_id": integer ID from the Available Projects list.
- "category": string from the Available Categories list.
- "subject": string, short summary.
- "description": string, full context."""

    return _run_inference(system_instruction, user_prompt, max_tokens=250, temp=0.1)

# --- TASK 2: Evaluate Draft Policy (STRICT - Temp 0.1) ---
def evaluate_policy(data):
    system_instruction = (
        f"You are Nexum, a strict corporate financial auditor based on the {MODEL_TYPE} architecture "
        f"running locally via 8-bit OpenVINO with a {CONTEXT_WINDOW}-token context window. "
        "You must output ONLY valid JSON format. Do not include conversational text or markdown."
    )
    user_prompt = f"""Review the following proposed expense draft against company policy.
Draft Amount: {data.get('amount', 0)}
Draft Category: {data.get('category', '')}
Draft Description: {data.get('description', '')}
Company Expense Policy:
{data.get('company_policy', '')}

Provide a JSON response with the following keys exactly:
- "decision": strictly "APPROVE", "REJECT", or "FLAG_FOR_HUMAN".
- "reason": A 1-sentence explanation of the decision."""

    return _run_inference(system_instruction, user_prompt, max_tokens=150, temp=0.1)

# --- TASK 3: Analyze Financials (CREATIVE - Temp 0.6) ---
def analyze_finances(data):
    system_instruction = (
        f"You are Nexum, a highly intelligent financial AI assistant based on the {MODEL_TYPE} architecture "
        f"running locally via 8-bit OpenVINO with a {CONTEXT_WINDOW}-token context window. "
        "You must output ONLY valid JSON format. Do not include conversational text or markdown."
    )
    user_prompt = f"""Analyze the following project data:
Total Budget: {data.get('total_budget', 0)}
Actual Spend: {data.get('actual_spend', 0)}
Due Date: {data.get('due_date', 'Unknown')}
Recent Transactions: {json.dumps(data.get('transactions', []))}
Additional Context: {data.get('user_context', '')}

Provide a JSON response with the following keys exactly:
- "variance": Estimated budget variance as a percentage (e.g., "18.75%").
- "variance_status": "Over Budget", "Under Budget", or "On Track".
- "projected_total": Estimated total spend at completion as a formatted currency string.
- "inflection_date": Predicted date funds run out (YYYY-MM-DD), or "N/A" if safe.
- "risk_level": Exactly one of: Low, Medium, High.
- "success_probability": An integer (0-100) representing confidence in staying under budget.
- "recommended_solutions": Detailed markdown text combining an executive summary and actionable advice.
- "predictions": A list of objects containing "x" (date string YYYY-MM-DD) and "y" (numeric cumulative spend) for the next 4-6 milestones to align with an Apex chart."""

    # Note the temp=0.6 here to allow for creative advice and graph generation
    return _run_inference(system_instruction, user_prompt, max_tokens=800, temp=0.6)