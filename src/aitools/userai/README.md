# User AI Tools

This directory contains local AI inference tools for user interactions in the Nexum project.

## Available Tools

### 1. GemmaLocalInference (`gemma_local.py`)
- **Purpose**: OpenVINO-optimized inference for Gemma models
- **Features**: CPU optimization, Hugging Face model support
- **Dependencies**: OpenVINO, Transformers, Optimum

### 2. SimpleGemmaInference (`gemma_simple_local.py`)
- **Purpose**: Universal adapter for LM Studio models
- **Features**: Auto-detects model formats, multiple inference backends
- **Dependencies**: Varies (auto-detects available)

### 3. GemmaGGUFInference (`gemma_gguf_local.py`)
- **Purpose**: GGUF format support (LM Studio compatible)
- **Features**: Direct GGUF loading, llama.cpp backend
- **Dependencies**: llama-cpp-python

## Quick Start

```python
# Import the tools
from src.aitools.userai import SimpleGemmaInference

# Initialize and use
inference = SimpleGemmaInference()
inference.create_simple_chat_interface()
```

## Usage

### Command Line
```bash
# Run from project root
python src/aitools/userai/gemma_simple_local.py

# Or OpenVINO optimized
python src/aitools/userai/gemma_local.py
```

### As Module
```python
from src.aitools.userai import GemmaLocalInference

# Initialize with custom model path
gemma = GemmaLocalInference("path/to/model")
gemma.load_model()
response = gemma.generate("Hello, how are you?")
```

## Model Support

- **LM Studio models**: Automatically detected
- **GGUF format**: Direct loading
- **SafeTensors/BIN**: Transformers compatible
- **OpenVINO optimized**: CPU acceleration

## Requirements

All tools work completely locally with no HTTP requests. Required dependencies are auto-detected and the tools will use the best available inference method.

## Integration

These tools are designed to integrate with the Nexum Symfony application for AI-powered features while maintaining complete local processing.
