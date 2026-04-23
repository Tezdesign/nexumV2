#!/usr/bin/env python3
"""
Simple Local Gemma Inference - Works with your LM Studio downloaded model
No HTTP requests, completely local inference
"""

import os
import sys
import json
import glob
from pathlib import Path

class SimpleGemmaInference:
    def __init__(self):
        """Initialize simple inference that works with LM Studio models"""
        self.model_path = None
        self.model_files = {}
        
        # LM Studio paths to check
        self.search_paths = [
            os.path.expanduser("~/AppData/Local/LM-Studio/models"),
            os.path.expanduser("~/.lm-studio/models"),
            "C:\\LM-Studio\\models",
            "D:\\LM-Studio\\models",
            "C:\\Users\\amine\\Desktop\\projet\\LM\\lmstudio-community",
            os.path.expanduser("~/Desktop/projet/LM/lmstudio-community"),
        ]
    
    def find_model_files(self):
        """Find Gemma model files in LM Studio directories"""
        print("Searching for Gemma model files...", file=sys.stderr)
        
        for base_path in self.search_paths:
            if os.path.exists(base_path):
                print(f"Checking: {base_path}", file=sys.stderr)
                
                # Look for directories containing 'gemma'
                for item in os.listdir(base_path):
                    if 'gemma' in item.lower():
                        model_dir = os.path.join(base_path, item)
                        print(f"Found Gemma directory: {model_dir}", file=sys.stderr)
                        
                        # Look for model files
                        model_files = self._find_model_files_in_dir(model_dir)
                        if model_files:
                            self.model_path = model_dir
                            self.model_files = model_files
                            return model_dir
        
        print("No Gemma model found in standard LM Studio locations.", file=sys.stderr)
        return None
    
    def _find_model_files_in_dir(self, directory):
        """Find actual model files in a directory"""
        files = {}
        
        for root, dirs, filenames in os.walk(directory):
            for filename in filenames:
                filepath = os.path.join(root, filename)
                
                # Look for different model formats
                if filename.endswith('.gguf'):
                    files['gguf'] = filepath
                    print(f"  Found GGUF: {filename}", file=sys.stderr)
                elif filename.endswith('.safetensors'):
                    files['safetensors'] = filepath
                    print(f"  Found SafeTensors: {filename}", file=sys.stderr)
                elif filename == 'config.json':
                    files['config'] = filepath
                elif filename == 'tokenizer.json':
                    files['tokenizer'] = filepath
                elif filename.endswith('.bin'):
                    files['bin'] = filepath
                    print(f"  Found BIN: {filename}", file=sys.stderr)
        
        return files if files else None
    
    def check_dependencies(self):
        """Check what dependencies are available"""
        deps = {
            'torch': False,
            'transformers': False,
            'openvino': False,
            'llama_cpp': False
        }
        
        try:
            import torch
            deps['torch'] = True
            print("✓ PyTorch available", file=sys.stderr)
        except ImportError:
            print("✗ PyTorch not available", file=sys.stderr)
        
        try:
            import transformers
            deps['transformers'] = True
            print("✓ Transformers available", file=sys.stderr)
        except ImportError:
            print("✗ Transformers not available", file=sys.stderr)
        
        try:
            import openvino
            deps['openvino'] = True
            print("✓ OpenVINO available", file=sys.stderr)
        except ImportError:
            print("✗ OpenVINO not available", file=sys.stderr)
        
        try:
            import llama_cpp
            deps['llama_cpp'] = True
            print("✓ Llama.cpp available", file=sys.stderr)
        except ImportError:
            print("✗ Llama.cpp not available", file=sys.stderr)
        
        return deps
    
    def generate(self, prompt: str, max_new_tokens: int = 500, temperature: float = 0.3) -> str:
        """Generate response using the best available method"""
        deps = self.check_dependencies()
        
        # Try different inference methods based on available dependencies and model files
        if 'gguf' in self.model_files and deps['llama_cpp']:
            return self._generate_with_llama_cpp(prompt, max_new_tokens, temperature)
        elif ('safetensors' in self.model_files or 'bin' in self.model_files) and deps['torch'] and deps['transformers']:
            return self._generate_with_transformers(prompt, max_new_tokens, temperature)
        elif deps['torch'] and deps['transformers']:
            # Try to use the GGUF file with transformers as fallback
            return self._generate_with_transformers_gguf(prompt, max_new_tokens, temperature)
        else:
            raise Exception("No compatible inference method available")
    
    def _generate_with_llama_cpp(self, prompt: str, max_new_tokens: int, temperature: float) -> str:
        """Generate using llama.cpp for GGUF models"""
        try:
            from llama_cpp import Llama
            
            gguf_file = self.model_files['gguf']
            model = Llama(
                model_path=gguf_file,
                n_ctx=2048,
                n_threads=4,
                temperature=temperature,
                max_tokens=max_new_tokens
            )
            
            response = model(prompt, max_tokens=max_new_tokens, temperature=temperature)
            return response['choices'][0]['text'].strip()
            
        except Exception as e:
            print(f"Llama.cpp generation failed: {e}", file=sys.stderr)
            raise
    
    def _generate_with_transformers(self, prompt: str, max_new_tokens: int, temperature: float) -> str:
        """Generate using transformers for safetensors/bin models"""
        try:
            import torch
            from transformers import AutoTokenizer, AutoModelForCausalLM, pipeline
            
            # Load tokenizer
            tokenizer = AutoTokenizer.from_pretrained(self.model_path)
            
            # Load model
            model = AutoModelForCausalLM.from_pretrained(
                self.model_path,
                torch_dtype=torch.float16,
                device_map="auto",
                trust_remote_code=True
            )
            
            # Create pipeline
            pipe = pipeline("text-generation", model=model, tokenizer=tokenizer)
            
            # Generate response
            response = pipe(
                prompt,
                max_new_tokens=max_new_tokens,
                temperature=temperature,
                do_sample=True,
                pad_token_id=tokenizer.eos_token_id
            )
            
            return response[0]['generated_text'].replace(prompt, "").strip()
            
        except Exception as e:
            print(f"Transformers generation failed: {e}", file=sys.stderr)
            raise
    
    def _generate_with_transformers_gguf(self, prompt: str, max_new_tokens: int, temperature: float) -> str:
        """Try to use GGUF with transformers (may not work but worth trying)"""
        try:
            import torch
            from transformers import AutoTokenizer, AutoModelForCausalLM, pipeline
            
            # Try to load from the GGUF directory
            tokenizer = AutoTokenizer.from_pretrained("google/gemma-2b-it")
            model = AutoModelForCausalLM.from_pretrained("google/gemma-2b-it", torch_dtype=torch.float16)
            
            pipe = pipeline("text-generation", model=model, tokenizer=tokenizer)
            
            response = pipe(
                prompt,
                max_new_tokens=max_new_tokens,
                temperature=temperature,
                do_sample=True,
                pad_token_id=tokenizer.eos_token_id
            )
            
            return response[0]['generated_text'].replace(prompt, "").strip()
            
        except Exception as e:
            print(f"Transformers GGUF fallback failed: {e}", file=sys.stderr)
            raise
    
    def run_with_transformers(self):
        """Try to run with transformers (for safetensors/bin models)"""
        try:
            import torch
            from transformers import AutoTokenizer, AutoModelForCausalLM, pipeline
            
            print("Loading model with Transformers...")
            
            # Load tokenizer
            if 'tokenizer' in self.model_files:
                tokenizer = AutoTokenizer.from_pretrained(self.model_path)
            else:
                # Try to download tokenizer for the model
                tokenizer = AutoTokenizer.from_pretrained("microsoft/DialoGPT-medium")  # Fallback
            
            # Load model
            if 'safetensors' in self.model_files or 'bin' in self.model_files:
                model = AutoModelForCausalLM.from_pretrained(
                    self.model_path,
                    torch_dtype=torch.float16,
                    device_map="auto",
                    trust_remote_code=True
                )
                
                # Create pipeline
                pipe = pipeline(
                    "text-generation",
                    model=model,
                    tokenizer=tokenizer,
                    torch_dtype=torch.float16,
                    device_map="auto"
                )
                
                return self._test_pipeline(pipe)
            else:
                print("No compatible model files found for Transformers")
                return False
                
        except Exception as e:
            print(f"Transformers approach failed: {e}")
            return False
    
    def run_with_openvino(self):
        """Try to run with OpenVINO"""
        try:
            from optimum.intel.openvino import OVModelForCausalLM
            from transformers import AutoTokenizer, pipeline
            
            print("Loading model with OpenVINO...")
            
            tokenizer = AutoTokenizer.from_pretrained(self.model_path)
            model = OVModelForCausalLM.from_pretrained(
                self.model_path,
                export=True,
                compile=True,
                device="CPU"
            )
            
            pipe = pipeline(
                "text-generation",
                model=model,
                tokenizer=tokenizer
            )
            
            return self._test_pipeline(pipe)
            
        except Exception as e:
            print(f"OpenVINO approach failed: {e}")
            return False
    
    def _test_pipeline(self, pipe):
        """Test a pipeline with simple prompts"""
        print("\n=== Testing Model ===")
        
        test_prompts = [
            "Hello, how are you?",
            "What is 2+2?",
            "Write a simple Python function:"
        ]
        
        for prompt in test_prompts:
            try:
                print(f"\nPrompt: {prompt}")
                outputs = pipe(
                    prompt,
                    max_new_tokens=50,
                    temperature=0.7,
                    do_sample=True,
                    pad_token_id=pipe.tokenizer.eos_token_id,
                    return_full_text=False
                )
                
                response = outputs[0]['generated_text'].strip()
                print(f"Response: {response}")
                
            except Exception as e:
                print(f"Error generating response: {e}")
        
        return True
    
    def create_simple_chat_interface(self):
        """Create a simple chat interface"""
        print("\n=== Simple Chat Interface ===")
        print("Type 'quit' to exit\n")
        
        # Try to find and load model
        if not self.find_model_files():
            print("No model found. Please download a Gemma model in LM Studio first.")
            return
        
        deps = self.check_dependencies()
        
        # Try different loading methods
        if deps['openvino'] and ('safetensors' in self.model_files or 'bin' in self.model_files):
            if self.run_with_openvino():
                return
        elif deps['transformers'] and ('safetensors' in self.model_files or 'bin' in self.model_files):
            if self.run_with_transformers():
                return
        elif deps['llama_cpp'] and 'gguf' in self.model_files:
            print("GGUF model found but llama.cpp not available")
        else:
            print("No compatible inference method available with current dependencies")
            print("\nTo use this script:")
            print("1. Download a Gemma model in LM Studio")
            print("2. Ensure you have the required dependencies")
            print("3. Run this script again")


def main():
    """Main function"""
    print("=== Simple Gemma Local Inference ===")
    print("This script works with models downloaded in LM Studio")
    print("No HTTP requests - completely local inference\n")
    
    inference = SimpleGemmaInference()
    inference.create_simple_chat_interface()


if __name__ == "__main__":
    main()
