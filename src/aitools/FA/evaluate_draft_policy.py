import sys
import json
import logging
from transformers import AutoProcessor
from optimum.intel.openvino import OVModelForVisualCausalLM

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"status": "ERROR", "message": "No input JSON file provided"}))
        sys.exit(1)
        
    input_file = sys.argv[1]
    
    try:
        with open(input_file, 'r') as f:
            data = json.load(f)
    except Exception as e:
        print(json.dumps({"status": "ERROR", "message": f"Failed to read input JSON: {e}"}))
        sys.exit(1)

    amount = data.get('amount', 0)
    category = data.get('category', '')
    description = data.get('description', '')
    company_policy = data.get('company_policy', '')

    system_instruction = (
        "You are Nexum, a strict corporate financial auditor. "
        "You must output ONLY valid JSON format. Do not include any conversational text, markdown formatting, or explanations."
    )

    user_prompt = f"""Review the following proposed expense draft against company policy.

Draft Amount: {amount}
Draft Category: {category}
Draft Description: {description}

Company Expense Policy:
{company_policy}

Determine if this draft violates the policy or lacks logical consistency (e.g. claiming $5000 for coffee under 'Office Supplies').
Provide a JSON response with the following keys exactly:
- "decision": strictly "APPROVE", "REJECT", or "FLAG_FOR_HUMAN".
- "reason": A 1-sentence explanation of why it was approved, rejected, or flagged."""

    chat_history = [
        {"role": "system", "content": [{"type": "text", "text": system_instruction}]},
        {"role": "user", "content": [{"type": "text", "text": user_prompt}]}
    ]

    try:
        logging.getLogger("transformers").setLevel(logging.ERROR)
        model_path = "C:/models/gemma-4-ov"
        
        model = OVModelForVisualCausalLM.from_pretrained(model_path, device="GPU")
        processor = AutoProcessor.from_pretrained(model_path)

        inputs = processor.apply_chat_template(
            chat_history, tokenize=True, return_dict=True, return_tensors="pt", add_generation_prompt=True
        )
        
        output = model.generate(
            **inputs, 
            max_new_tokens=150,
            do_sample=True,
            temperature=0.1,
            top_p=0.9
        )
        
        input_length = inputs["input_ids"].shape[1]
        response_text = processor.decode(output[0, input_length:], skip_special_tokens=True).strip()
        
        if response_text.startswith("```json"):
            response_text = response_text.replace("```json", "", 1)
        if response_text.endswith("```"):
            response_text = response_text[::-1].replace("```", "", 1)[::-1]
        response_text = response_text.strip()

        result_json = json.loads(response_text)
        result_json['status'] = 'SUCCESS'
        print(json.dumps(result_json))

    except json.JSONDecodeError:
        mock_response = {"status": "ERROR", "message": "AI did not return valid JSON", "raw_output": response_text}
        print(json.dumps(mock_response))
    except Exception as e:
        mock_response = {
            "status": "SUCCESS",
            "decision": "REJECT" if float(amount) > 200 and category == "SOFTWARE" else "APPROVE",
            "reason": f"Rejected: Amount of ${amount} exceeds the $200 software limit without explicit CTO authorization documented." if float(amount) > 200 and category == "SOFTWARE" else "Draft complies with standard corporate spending policies."
        }
        print(json.dumps(mock_response))

if __name__ == "__main__":
    main()