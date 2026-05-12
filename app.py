from flask import Flask, request, jsonify
from flask_cors import CORS
import nexum_engine # Imports your model and loads it into the GPU

app = Flask(__name__)
CORS(app)

# 1. Endpoint for parse_draft_intent
@app.route('/api/nexum/intent', methods=['POST'])
def handle_intent():
    data = request.json
    if not data:
        return jsonify({"status": "ERROR", "message": "No JSON payload provided"}), 400

    result = nexum_engine.extract_draft_intent(data)

    # Return 500 if the engine caught an error, 200 if successful
    status_code = 200 if result.get("status") == "SUCCESS" else 500
    return jsonify(result), status_code

# 2. Endpoint for evaluate_draft_policy
@app.route('/api/nexum/evaluate', methods=['POST'])
def handle_evaluate():
    data = request.json
    if not data:
        return jsonify({"status": "ERROR", "message": "No JSON payload provided"}), 400

    result = nexum_engine.evaluate_policy(data)

    status_code = 200 if result.get("status") == "SUCCESS" else 500
    return jsonify(result), status_code

# 3. Endpoint for analyze_financials
@app.route('/api/nexum/analyze', methods=['POST'])
def handle_analyze():
    data = request.json
    if not data:
        return jsonify({"status": "ERROR", "message": "No JSON payload provided"}), 400

    result = nexum_engine.analyze_finances(data)

    status_code = 200 if result.get("status") == "SUCCESS" else 500
    return jsonify(result), status_code

if __name__ == '__main__':
    print("Starting Flask Server on port 5000...")
    # Bind to 0.0.0.0 to allow LAN access
    app.run(host='0.0.0.0', port=5000, debug=False)