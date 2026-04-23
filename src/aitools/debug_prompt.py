#!/usr/bin/env python3
import re

# Test the exact prompt format from AIAuditService
prompt = """Analyze these specific audit metrics and provide data-driven insights:

USER METRICS:
- Total Users: 1250
- Active Users: 1180 (94.4% engagement)
- User Engagement Rate: 94.4%

RECLAMATION METRICS:
- Total Reclamations: 320
- Resolved: 245 (76.6% resolution rate)
- Pending: 28 (8.8% pending rate)

Provide specific analysis based on these exact percentages:
1. System health assessment using 94.4% engagement and 76.6% resolution rate
2. Key metrics summary highlighting these specific performance indicators
3. Trend analysis based on 8.8% pending rate and user metrics
4. Action items specific to these performance levels

Focus analysis on these actual numbers, not generic statements.
Format as JSON: {"health_assessment": "specific assessment", "metrics_summary": ["specific metrics"], "trend_analysis": ["specific trends"], "action_items": ["specific actions"]}"""

print("Testing regex patterns against actual prompt:")
print("=" * 50)

patterns = {
    'User Engagement Rate': r'User Engagement Rate: (\d+\.?\d*)%',
    'Reclamation Resolution': r'Resolved: (\d+) \((\d+\.?\d*)% resolution rate\)',
    'Reclamation Pending': r'Pending: (\d+) \((\d+\.?\d*)% pending rate\)',
    'Total Users': r'- Total Users: (\d+)',
    'Total Reclamations': r'- Total Reclamations: (\d+)',
    'Active Users': r'- Active Users: (\d+)',
    'Resolved Reclamations': r'- Resolved: (\d+)',
    'Pending Reclamations': r'- Pending: (\d+)'
}

for name, pattern in patterns.items():
    match = re.search(pattern, prompt)
    if match:
        print(f"✓ {name}: MATCH FOUND")
        for i, group in enumerate(match.groups()):
            print(f"  Group {i+1}: {group}")
    else:
        print(f"✗ {name}: NO MATCH")
    print()

print("Full prompt text:")
print(prompt)
