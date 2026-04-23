#!/usr/bin/env python3
"""
Local Gemma-4-e4b inference using OpenVINO
No HTTP requests, completely local inference
"""

import os
import sys
import torch
from transformers import AutoTokenizer, AutoModelForCausalLM, pipeline
from optimum.intel.openvino import OVModelForCausalLM
from pathlib import Path

class GemmaLocalInference:
    def __init__(self, model_id="google/gemma-2b-it"):
        """
        Initialize Gemma model locally using OpenVINO
        
        Args:
            model_id: Model identifier or local path
        """
        self.model_id = model_id
        self.model = None
        self.tokenizer = None
        self.pipe = None
        
        # Try to find model in common LM Studio locations first
        self.lm_studio_paths = [
            os.path.expanduser("~/AppData/Local/LM-Studio/models"),
            os.path.expanduser("~/.lm-studio/models"),
            os.path.expanduser("~/lm-studio/models"),
        ]
        
    def find_lm_studio_model(self):
        """Try to find the model in LM Studio directories"""
        for base_path in self.lm_studio_paths:
            if os.path.exists(base_path):
                # Look for Gemma model directories
                for root, dirs, files in os.walk(base_path):
                    if "gemma" in root.lower() and "4b" in root.lower():
                        print(f"Found potential model directory: {root}")
                        # Check for model files
                        if any(f.endswith(('.bin', '.safetensors', '.gguf')) for f in files):
                            return root
        return None
    
    def load_model(self, use_openvino=True):
        """Load the model using OpenVINO for optimal performance"""
        print("Loading Gemma-4-e4b model...")
        
        # First try to find in LM Studio
        lm_studio_path = self.find_lm_studio_model()
        if lm_studio_path:
            print(f"Using LM Studio model from: {lm_studio_path}")
            model_path = lm_studio_path
        else:
            print("Model not found in LM Studio, using Hugging Face...")
            model_path = self.model_id
        
        try:
            if use_openvino:
                print("Loading with OpenVINO optimization...")
                # Load tokenizer
                self.tokenizer = AutoTokenizer.from_pretrained(model_path)
                
                # Convert and load model with OpenVINO
                self.model = OVModelForCausalLM.from_pretrained(
                    model_path,
                    export=True,  # Export to OpenVINO format if needed
                    compile=True,  # Compile for inference
                    device="CPU"   # Use CPU, change to "GPU" if available
                )
                
                # Create pipeline
                self.pipe = pipeline(
                    "text-generation",
                    model=self.model,
                    tokenizer=self.tokenizer,
                    torch_dtype=torch.bfloat16,
                    device_map="auto"
                )
                
            else:
                print("Loading with standard PyTorch...")
                self.tokenizer = AutoTokenizer.from_pretrained(model_path)
                self.model = AutoModelForCausalLM.from_pretrained(
                    model_path,
                    torch_dtype=torch.bfloat16,
                    device_map="auto"
                )
                
                self.pipe = pipeline(
                    "text-generation",
                    model=self.model,
                    tokenizer=self.tokenizer,
                    torch_dtype=torch.bfloat16,
                    device_map="auto"
                )
                
            print("Model loaded successfully!")
            return True
            
        except Exception as e:
            print(f"Error loading model: {e}")
            return False
    
    def generate(self, prompt, max_new_tokens=512, temperature=0.7, do_sample=True):
        """
        Generate text locally without any HTTP requests
        
        Args:
            prompt: Input text prompt
            max_new_tokens: Maximum number of tokens to generate
            temperature: Sampling temperature
            do_sample: Whether to use sampling
            
        Returns:
            Generated text
        """
        if not self.pipe:
            raise RuntimeError("Model not loaded. Call load_model() first.")
        
        try:
            # Generate response locally
            outputs = self.pipe(
                prompt,
                max_new_tokens=max_new_tokens,
                temperature=temperature,
                do_sample=do_sample,
                pad_token_id=self.tokenizer.eos_token_id,
                return_full_text=False  # Only return generated text
            )
            
            return outputs[0]['generated_text'].strip()
            
        except Exception as e:
            print(f"Error during generation: {e}")
            return None
    
    def chat(self, message, history=None):
        """
        Chat interface for conversational AI
        
        Args:
            message: User message
            history: Chat history (optional)
            
        Returns:
            AI response
        """
        if history is None:
            history = []
        
        # Format prompt with history if provided
        if history:
            context = "\n".join([f"User: {h['user']}\nAssistant: {h['assistant']}" for h in history])
            prompt = f"{context}\nUser: {message}\nAssistant: "
        else:
            prompt = f"User: {message}\nAssistant: "
        
        response = self.generate(prompt)
        
        # Add to history
        if response:
            history.append({"user": message, "assistant": response})
        
        return response, history


def test_inference():
    """Test the local inference"""
    print("=== Gemma Local Inference Test ===")
    
    # Initialize the inference engine
    gemma = GemmaLocalInference()
    
    # Load the model
    if not gemma.load_model():
        print("Failed to load model. Exiting.")
        return
    
    print("\n=== Test Generation ===")
    
    # Test 1: Simple prompt
    prompt1 = "What is artificial intelligence?"
    response1 = gemma.generate(prompt1, max_new_tokens=100)
    print(f"Prompt: {prompt1}")
    print(f"Response: {response1}\n")
    
    # Test 2: Code generation
    prompt2 = "Write a Python function to calculate factorial:"
    response2 = gemma.generate(prompt2, max_new_tokens=150)
    print(f"Prompt: {prompt2}")
    print(f"Response: {response2}\n")
    
    # Test 3: Chat interface
    print("=== Chat Interface Test ===")
    history = []
    
    messages = [
        "Hello, how are you?",
        "Can you help me with Python programming?",
        "What's the difference between list and tuple?"
    ]
    
    for msg in messages:
        response, history = gemma.chat(msg, history)
        print(f"User: {msg}")
        print(f"Assistant: {response}\n")


if __name__ == "__main__":
    # Run test if script is executed directly
    test_inference()
