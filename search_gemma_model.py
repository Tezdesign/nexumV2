#!/usr/bin/env python3
"""
Script to search for google/gemma-4-e4b model in LM Studio
"""
import os
import sys
from pathlib import Path

def search_for_gemma_model():
    """Search for google/gemma-4-e4b model in common locations"""
    
    # Extended search paths including user-specific locations
    search_paths = [
        os.path.expanduser("~/AppData/Local/LM-Studio/models"),
        os.path.expanduser("~/AppData/Roaming/LM-Studio/models"),
        os.path.expanduser("~/.lm-studio/models"),
        "C:\\LM-Studio\\models",
        "D:\\LM-Studio\\models",
        "E:\\LM-Studio\\models",
        "F:\\LM-Studio\\models",
        os.path.expanduser("~/Downloads"),
        "C:\\Users\\amine\\Downloads",
        os.path.expanduser("~/Desktop"),
    ]
    
    # Search patterns for gemma-4-e4b
    search_patterns = [
        "gemma-4-e4b",
        "gemma_4_e4b", 
        "gemma4e4b",
        "gemma-4b",
        "gemma_4b",
        "gemma4b",
        "gemma",
        "google"
    ]
    
    found_models = []
    
    print("🔍 Searching for google/gemma-4-e4b model...")
    print()
    
    for base_path in search_paths:
        if os.path.exists(base_path):
            print(f"📁 Checking: {base_path}")
            
            try:
                # Walk through all subdirectories
                for root, dirs, files in os.walk(base_path):
                    # Check if directory name matches our patterns
                    dir_name = os.path.basename(root).lower()
                    for pattern in search_patterns:
                        if pattern in dir_name:
                            print(f"  ✅ Found potential model directory: {os.path.basename(root)}")
                            
                            # Look for model files
                            model_files = []
                            for file in files:
                                if file.endswith(('.gguf', '.safetensors', '.bin', '.pth')):
                                    model_files.append(file)
                            
                            if model_files:
                                print(f"    📄 Model files: {', '.join(model_files)}")
                                found_models.append({
                                    'path': root,
                                    'files': model_files,
                                    'directory': os.path.basename(root)
                                })
                            break
                            
            except Exception as e:
                print(f"  ❌ Error accessing {base_path}: {e}")
        else:
            print(f"📁 Path not found: {base_path}")
    
    return found_models

def check_lm_studio_running():
    """Check if LM Studio is currently running"""
    try:
        import psutil
        for proc in psutil.process_iter(['name']):
            if 'lm-studio' in proc.info['name'].lower():
                return True
        return False
    except ImportError:
        return "psutil not available"

def main():
    print("=== Google Gemma-4-E4B Model Search ===")
    print()
    
    # Check if LM Studio is running
    lm_studio_status = check_lm_studio_running()
    if lm_studio_status is True:
        print("✅ LM Studio is currently running")
    elif lm_studio_status == "psutil not available":
        print("❓ Cannot check if LM Studio is running (psutil not installed)")
    else:
        print("❌ LM Studio is not currently running")
    print()
    
    # Search for the model
    models = search_for_gemma_model()
    print()
    
    if models:
        print("🎉 MODEL FOUND!")
        print("=" * 50)
        for i, model in enumerate(models, 1):
            print(f"{i}. Directory: {model['directory']}")
            print(f"   Path: {model['path']}")
            print(f"   Files: {', '.join(model['files'])}")
            print()
        
        print("📋 NEXT STEPS:")
        print("1. The AI system should now be able to find this model")
        print("2. Test the AI system with: python src/aitools/userai/ai_audit_wrapper.py 'test prompt'")
        print("3. If still not working, we may need to update the search paths")
        
    else:
        print("❌ MODEL NOT FOUND")
        print("=" * 50)
        print("📋 SETUP NEEDED:")
        print("1. Open LM Studio")
        print("2. Search for 'google/gemma-4-e4b' or 'gemma-4-e4b'")
        print("3. Download the model")
        print("4. Note where it's saved")
        print("5. Run this script again")
        print()
        print("💡 TIP: You can also download any Gemma variant (gemma-2b, gemma-7b, etc.)")

if __name__ == "__main__":
    main()
