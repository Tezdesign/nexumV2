#!/usr/bin/env python3
import re

# Test the exact prompt format
prompt = """Analyze these specific audit metrics and provide data-driven insights: USER METRICS: - Total Users: 1250 - Active Users: 1180 (94.4% engagement) - User Engagement Rate: 94.4% RECLAMATION METRICS: - Total Reclamations: 320 - Resolved: 245 (76.6% resolution rate) - Pending: 28 (8.8% pending rate)"""

print("Testing regex patterns against prompt:")
print("PROMPT:", prompt)
print()

# Test each pattern
patterns = {
    'User Engagement Rate': r'User Engagement Rate: (\d+\.?\d*)%',
    'Reclamation Resolution': r'Resolved: (\d+) \((\d+\.?\d*)% resolution rate\)',
    'Reclamation Pending': r'Pending: (\d+) \((\d+\.?\d*)% pending rate\)',
    'Total Users': r'Total Users: (\d+)',
    'Total Reclamations': r'Total Reclamations: (\d+)'
}

for name, pattern in patterns.items():
    match = re.search(pattern, prompt)
    if match:
        print(f"✓ {name}: MATCH FOUND")
        if match.groups():
            for i, group in enumerate(match.groups()):
                print(f"  Group {i+1}: {group}")
    else:
        print(f"✗ {name}: NO MATCH")
    print()
