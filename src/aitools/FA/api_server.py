from flask import Flask, request, jsonify
import subprocess
import json
import os
import tempfile

app = Flask(__name__)

# Helper to run the existing python scripts
def run_ai_script(script_name, payload):
    # Determine absolute path to the script
    base_dir = os.path.dirname(os.path.abspath(__file__))
    script_path = os.path.join(base_dir, script_name)
    
    if not os.path.exists(script_path):
        return {"status": "ERROR", "message": f"Script not found: {script_name}"}

    # Write payload to a temporary JSON file
    fd, temp_path = tempfile.mkstemp(suffix=".json", text=True)
    try:
        with os.fdopen(fd, 'w') as f:
            json.dump(payload, f)
            
        # Execute the python script using subprocess
        result = subprocess.run(
            ["python", script_path, temp_path],
            capture_output=True,
            text=True,
            timeout=300
        )
        
        if result.returncode != 0:
            return {
                "status": "ERROR", 
                "message": "Python script failed", 
                "raw_output": result.stdout,
                "error": result.stderr
            }
            
        # Parse the JSON output from the script
        try:
            return json.loads(result.stdout)
        except json.JSONDecodeError:
            return {
                "status": "ERROR",
                "message": "Failed to decode JSON from script output",
                "raw_output": result.stdout
            }
    except subprocess.TimeoutExpired:
        return {"status": "ERROR", "message": "Python script timed out after 300 seconds"}
    except Exception as e:
        return {"status": "ERROR", "message": str(e)}
    finally:
        if os.path.exists(temp_path):
            os.remove(temp_path)

@app.route('/api/evaluate_draft_policy', methods=['POST'])
def evaluate_draft_policy():
    payload = request.get_json()
    if not payload:
        return jsonify({"status": "ERROR", "message": "No JSON payload provided"}), 400
    
    result = run_ai_script("evaluate_draft_policy.py", payload)
    return jsonify(result)

@app.route('/api/analyze_financials', methods=['POST'])
def analyze_financials():
    payload = request.get_json()
    if not payload:
        return jsonify({"status": "ERROR", "message": "No JSON payload provided"}), 400
    
    result = run_ai_script("analyze_financials.py", payload)
    return jsonify(result)

@app.route('/api/parse_draft_intent', methods=['POST'])
def parse_draft_intent():
    payload = request.get_json()
    if not payload:
        return jsonify({"status": "ERROR", "message": "No JSON payload provided"}), 400
    
    result = run_ai_script("parse_draft_intent.py", payload)
    return jsonify(result)

if __name__ == '__main__':
    # Listen on all interfaces so the Linux server can reach it
    app.run(host='0.0.0.0', port=5000, debug=True)
