#!/usr/bin/env python3
import re

# Test the exact prompt format
prompt = """Analyze these specific audit metrics and provide data-driven insights: USER METRICS: - Total Users: 1250 - Active Users: 1180 (94.4% engagement) - User Engagement Rate: 94.4% RECLAMATION METRICS: - Total Reclamations: 320 - Resolved: 245 (76.6% resolution rate) - Pending: 28 (8.8% pending rate)"""

prompt_lower = prompt.lower()

print("Testing conditional logic:")
print("=" * 50)
print(f"Prompt lower: {prompt_lower}")
print()

# Test conditions
has_summary = "summary" in prompt_lower or "overview" in prompt_lower
has_audit_metrics = "audit metrics" in prompt_lower or "specific audit metrics" in prompt_lower
has_reclamation = "reclamation" in prompt_lower

print(f"Has 'summary' or 'overview': {has_summary}")
print(f"Has 'audit metrics': {has_audit_metrics}")
print(f"Has 'reclamation': {has_reclamation}")
print()

# Test the combined condition
summary_condition = has_summary and has_audit_metrics
print(f"Summary condition (summary AND audit_metrics): {summary_condition}")
print()

# Test what would be triggered
if summary_condition:
    print("✓ Would trigger SUMMARY logic")
elif has_reclamation:
    print("✗ Would trigger RECLAMATION logic (this is the problem)")
else:
    print("? Would trigger DEFAULT logic")
