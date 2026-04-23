#!/usr/bin/env python3
"""
Script to find LM Studio models and help set up the AI system
"""
import os
import sys
from pathlib import Path

def find_lm_studio_models():
    """Find LM Studio model directories"""
    possible_paths = [
        os.path.expanduser("~/AppData/Local/LM-Studio/models"),
        os.path.expanduser("~/.lm-studio/models"),
        "C:\\LM-Studio\\models",
        "D:\\LM-Studio\\models",
        "E:\\LM-Studio\\models",
    ]
    
    found_models = []
    
    for path in possible_paths:
        if os.path.exists(path):
            print(f"✓ Found LM Studio directory: {path}")
            try:
                items = os.listdir(path)
                model_dirs = [item for item in items if os.path.isdir(os.path.join(path, item))]
                if model_dirs:
                    print(f"  Model directories found: {len(model_dirs)}")
                    for model_dir in model_dirs[:5]:  # Show first 5
                        full_path = os.path.join(path, model_dir)
                        print(f"    - {model_dir}")
                        
                        # Check for model files
                        model_files = []
                        for root, dirs, files in os.walk(full_path):
                            for file in files:
                                if file.endswith(('.gguf', '.safetensors', '.bin', '.pth')):
                                    model_files.append(file)
                        
                        if model_files:
                            print(f"      Files: {', '.join(model_files[:3])}")
                            found_models.append((full_path, model_files))
                else:
                    print("  No model directories found")
            except Exception as e:
                print(f"  Error accessing directory: {e}")
        else:
            print(f"✗ Path not found: {path}")
    
    return found_models

def check_dependencies():
    """Check what AI dependencies are available"""
    deps = {}
    
    try:
        import torch
        deps['torch'] = True
        print("✓ PyTorch available")
    except ImportError:
        deps['torch'] = False
        print("✗ PyTorch not available")
    
    try:
        import transformers
        deps['transformers'] = True
        print("✓ Transformers available")
    except ImportError:
        deps['transformers'] = False
        print("✗ Transformers not available")
    
    try:
        import openvino
        deps['openvino'] = True
        print("✓ OpenVINO available")
    except ImportError:
        deps['openvino'] = False
        print("✗ OpenVINO not available")
    
    try:
        import llama_cpp
        deps['llama_cpp'] = True
        print("✓ Llama.cpp available")
    except ImportError:
        deps['llama_cpp'] = False
        print("✗ Llama.cpp not available")
    
    return deps

def main():
    print("=== LM Studio Model Finder ===")
    print()
    
    print("1. Searching for LM Studio directories...")
    models = find_lm_studio_models()
    print()
    
    print("2. Checking AI dependencies...")
    deps = check_dependencies()
    print()
    
    if models:
        print("=== SETUP INSTRUCTIONS ===")
        print("Models found! The AI system should work automatically.")
        print("If it's still not working, you may need to:")
        print("1. Install missing dependencies:")
        
        if not deps['torch']:
            print("   pip install torch")
        if not deps['transformers']:
            print("   pip install transformers")
        if not deps['llama_cpp']:
            print("   pip install llama-cpp-python")
        
        print("2. Ensure you have a Gemma model downloaded")
        print("3. Try the AI system again")
    else:
        print("=== SETUP NEEDED ===")
        print("No LM Studio models found. Please:")
        print("1. Open LM Studio")
        print("2. Download a Gemma model (any variant)")
        print("3. Note the download location")
        print("4. Update the search paths in gemma_simple_local.py if needed")

if __name__ == "__main__":
    main()
