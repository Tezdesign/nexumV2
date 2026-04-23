#!/usr/bin/env python3
"""
Local Gemma-4-e4b inference using GGUF format (LM Studio compatible)
No HTTP requests, completely local inference using llama.cpp
"""

import os
import sys
from pathlib import Path

# Try to install llama.cpp if not available
try:
    from llama_cpp import Llama
except ImportError:
    print("Installing llama-cpp-python for GGUF support...")
    import subprocess
    subprocess.check_call([sys.executable, "-m", "pip", "install", "llama-cpp-python"])
    from llama_cpp import Llama

class GemmaGGUFInference:
    def __init__(self, model_path=None):
        """
        Initialize Gemma model using GGUF format
        
        Args:
            model_path: Path to GGUF model file
        """
        self.model_path = model_path
        self.model = None
        
        # Common LM Studio model locations
        self.lm_studio_paths = [
            os.path.expanduser("~/AppData/Local/LM-Studio/models"),
            os.path.expanduser("~/.lm-studio/models"),
            os.path.expanduser("~/lm-studio/models"),
        ]
    
    def find_gguf_model(self):
        """Find GGUF model in LM Studio directories"""
        print("Searching for Gemma GGUF model...")
        
        for base_path in self.lm_studio_paths:
            if os.path.exists(base_path):
                print(f"Checking: {base_path}")
                for root, dirs, files in os.walk(base_path):
                    for file in files:
                        if file.endswith('.gguf') and 'gemma' in file.lower():
                            full_path = os.path.join(root, file)
                            print(f"Found GGUF model: {full_path}")
                            return full_path
        
        print("No GGUF model found in LM Studio directories.")
        return None
    
    def load_model(self, n_ctx=2048, n_gpu_layers=0):
        """
        Load GGUF model
        
        Args:
            n_ctx: Context size
            n_gpu_layers: Number of layers to offload to GPU (0 = CPU only)
        """
        if not self.model_path:
            self.model_path = self.find_gguf_model()
        
        if not self.model_path:
            print("No model path specified or found. Please provide a GGUF model file.")
            return False
        
        if not os.path.exists(self.model_path):
            print(f"Model file not found: {self.model_path}")
            return False
        
        try:
            print(f"Loading model: {self.model_path}")
            self.model = Llama(
                model_path=self.model_path,
                n_ctx=n_ctx,
                n_gpu_layers=n_gpu_layers,
                verbose=False
            )
            print("Model loaded successfully!")
            return True
            
        except Exception as e:
            print(f"Error loading model: {e}")
            return False
    
    def generate(self, prompt, max_tokens=512, temperature=0.7, stop=None):
        """
        Generate text locally
        
        Args:
            prompt: Input text prompt
            max_tokens: Maximum tokens to generate
            temperature: Sampling temperature
            stop: Stop sequences
            
        Returns:
            Generated text
        """
        if not self.model:
            raise RuntimeError("Model not loaded. Call load_model() first.")
        
        try:
            output = self.model(
                prompt,
                max_tokens=max_tokens,
                temperature=temperature,
                stop=stop or ["User:", "Human:", "\n\n"],
                echo=False
            )
            
            return output['choices'][0]['text'].strip()
            
        except Exception as e:
            print(f"Error during generation: {e}")
            return None
    
    def chat(self, message, history=None):
        """
        Chat interface
        
        Args:
            message: User message
            history: Chat history
            
        Returns:
            Response and updated history
        """
        if history is None:
            history = []
        
        # Build prompt from history
        prompt_parts = []
        
        # Add system prompt for Gemma
        prompt_parts.append("You are a helpful AI assistant. Provide clear and accurate responses.")
        
        # Add conversation history
        for turn in history:
            prompt_parts.append(f"User: {turn['user']}")
            prompt_parts.append(f"Assistant: {turn['assistant']}")
        
        # Add current message
        prompt_parts.append(f"User: {message}")
        prompt_parts.append("Assistant: ")
        
        prompt = "\n".join(prompt_parts)
        
        response = self.generate(prompt)
        
        if response:
            history.append({"user": message, "assistant": response})
        
        return response, history


def test_gguf_inference():
    """Test GGUF inference"""
    print("=== Gemma GGUF Local Inference Test ===")
    
    # Initialize
    gemma = GemmaGGUFInference()
    
    # Load model
    if not gemma.load_model():
        print("Failed to load model.")
        print("\nTo use this script:")
        print("1. Download a Gemma GGUF model from LM Studio")
        print("2. Or provide model path: GemmaGGUFInference('/path/to/model.gguf')")
        return
    
    print("\n=== Test Generation ===")
    
    # Test prompts
    test_prompts = [
        "What is artificial intelligence?",
        "Write a simple Python hello world function:",
        "Explain machine learning in simple terms:"
    ]
    
    for prompt in test_prompts:
        print(f"Prompt: {prompt}")
        response = gemma.generate(prompt, max_tokens=150)
        print(f"Response: {response}\n")
        print("-" * 50)
    
    # Test chat interface
    print("\n=== Chat Interface Test ===")
    history = []
    
    chat_messages = ["Hello!", "How can you help me?"]
    
    for msg in chat_messages:
        response, history = gemma.chat(msg, history)
        print(f"User: {msg}")
        print(f"Assistant: {response}\n")


if __name__ == "__main__":
    test_gguf_inference()
