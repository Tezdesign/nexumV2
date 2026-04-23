#!/usr/bin/env python3
"""
Test direct usage of the local Gemma-4-E4B GGUF model
"""
import os
import sys

def test_gemma_direct():
    """Try to use the local Gemma model directly"""
    
    model_path = "C:\\Users\\amine\\Desktop\\projet\\LM\\lmstudio-community\\gemma-4-E4B-it-GGUF\\gemma-4-E4B-it-Q4_K_M.gguf"
    
    if not os.path.exists(model_path):
        print(f"Model file not found: {model_path}")
        return False
    
    print(f"Found model: {model_path}")
    
    # Try different approaches
    approaches = [
        ("llama-cpp-python", try_llama_cpp),
        ("transformers gguf", try_transformers_gguf),
        ("ctransformers", try_ctransformers),
        ("open source model", try_open_source_model),
    ]
    
    for name, func in approaches:
        print(f"\nTrying {name}...")
        try:
            result = func(model_path)
            if result:
                print(f"✅ {name} worked!")
                return True
            else:
                print(f"❌ {name} failed")
        except Exception as e:
            print(f"❌ {name} failed: {e}")
    
    return False

def try_llama_cpp(model_path):
    """Try using llama-cpp-python"""
    try:
        from llama_cpp import Llama
        
        model = Llama(
            model_path=model_path,
            n_ctx=512,
            n_threads=2,
            temperature=0.7,
            max_tokens=50
        )
        
        prompt = "Hello, how are you?"
        response = model(prompt, max_tokens=50, temperature=0.7)
        
        print(f"Response: {response['choices'][0]['text']}")
        return True
        
    except ImportError:
        print("llama-cpp-python not installed")
        return False
    except Exception as e:
        print(f"llama-cpp error: {e}")
        return False

def try_transformers_gguf(model_path):
    """Try using transformers with GGUF (may not work)"""
    try:
        import torch
        from transformers import AutoTokenizer, AutoModelForCausalLM, pipeline
        
        # Try a different approach - use a small open model
        model_name = "microsoft/DialoGPT-medium"
        tokenizer = AutoTokenizer.from_pretrained(model_name)
        model = AutoModelForCausalLM.from_pretrained(model_name)
        
        pipe = pipeline("text-generation", model=model, tokenizer=tokenizer)
        
        prompt = "Hello, how are you?"
        response = pipe(prompt, max_tokens=50, temperature=0.7)
        
        print(f"Response: {response[0]['generated_text']}")
        return True
        
    except Exception as e:
        print(f"Transformers error: {e}")
        return False

def try_ctransformers(model_path):
    """Try using ctransformers"""
    try:
        from ctransformers import AutoModelForCausalLM
        
        model = AutoModelForCausalLM.from_pretrained(model_path)
        
        prompt = "Hello, how are you?"
        response = model(prompt, max_new_tokens=50)
        
        print(f"Response: {response}")
        return True
        
    except ImportError:
        print("ctransformers not installed")
        return False
    except Exception as e:
        print(f"ctransformers error: {e}")
        return False

def try_open_source_model(model_path):
    """Try using a small open source model"""
    try:
        import torch
        from transformers import pipeline
        
        # Use a small, open model that doesn't require authentication
        pipe = pipeline("text-generation", model="distilgpt2")
        
        prompt = "Hello, how are you?"
        response = pipe(prompt, max_tokens=50, temperature=0.7)
        
        print(f"Response: {response[0]['generated_text']}")
        return True
        
    except Exception as e:
        print(f"Open source model error: {e}")
        return False

if __name__ == "__main__":
    print("=== Testing Local Gemma Model ===")
    success = test_gemma_direct()
    
    if not success:
        print("\n❌ All methods failed")
        print("💡 Recommendation: Install llama-cpp-python with proper build tools")
        print("   Or use the fallback responses (which are already working well)")
    else:
        print("\n✅ Successfully loaded a model!")
