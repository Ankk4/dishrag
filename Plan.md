# Proof-of-concept

RAG pipeline in its simplest form consists of following steps:

1. Gather,
2. Chunk,
3. Embed,
4. Vectorize
5. Generate

We also need a way to orchestrate the pipeline and to get user input.

```mermaid
graph TD

    subgraph Gather
        direction TB
        G[Sources]:::sources
        H[Scrapers]:::scrapers
        I[Documents]:::documents
        K[APIs]:::apis
        Me[Media]:::mediaSources
        
        G -->|Websites: HTML, feeds, files| H
        H -->|Extracted/clean text| I
        G -->|API sources| K
        K -->|Normalized records/text| I
        Me -->|Transcribed text| I
    end

    subgraph Chunk
        direction TB
        J[Well-structured Chunks]:::wellStructured
        L[Document Splitters]:::documentSplitters
        M[Text Splitters]:::textSplitters

        L --> M
        M --> J
    end

    subgraph Embed
        direction TB
        P[Embedding Model]:::embeddingModel
        N[Embeddings]:::embeddings
        VDB[(Vector Database)]:::vectorDatabase

        J --> P
        P -->|Process chunks to number representations| N
        N -->|Write to vector database| VDB
    end

    subgraph Generate
        direction TB
        R[Prompt]:::prompt
        S[Generation Model]:::generationModel
        T[Output]:::output

        S -->|Generate response| T
    end

    subgraph Orchestrator
        direction TB
        U[End user]:::external 
        API[RAG API / Orchestrator]:::api
        CTX[/Retrieved context/]:::retrieval

        U   -->|Question| API
        API -->|Retrieve context| CTX
        VDB -.-> CTX
        CTX --> R
        API -->|System and user messages| R
        R   -->|Final prompt| S
        S   -->|Generate response| T
        T   -->|Response| API
        API -->|Answer to user| U:::external
    end

    I --> L
    I --> M

    %% High-contrast palette (dark text on light fills)
    classDef sources fill:#FFE0B2,stroke:#333,stroke-width:2px,color:#111;
    classDef scrapers fill:#FFF3E0,stroke:#333,stroke-width:2px,color:#111;
    classDef documents fill:#FFF3E0,stroke:#333,stroke-width:2px,color:#111;
    classDef apis fill:#FFF3E0,stroke:#333,stroke-width:2px,color:#111;
    classDef mediaSources fill:#FFF3E0,stroke:#333,stroke-width:2px,color:#111;

    classDef wellStructured fill:#EDE7F6,stroke:#333,stroke-width:2px,color:#111;
    classDef documentSplitters fill:#E8F5E9,stroke:#333,stroke-width:2px,color:#111;
    classDef textSplitters fill:#E8F5E9,stroke:#333,stroke-width:2px,color:#111;

    classDef embeddingModel fill:#E3F2FD,stroke:#333,stroke-width:2px,color:#111;
    classDef embeddings fill:#E3F2FD,stroke:#333,stroke-width:2px,color:#111;
    classDef vectorDatabase fill:#FCE4EC,stroke:#333,stroke-width:2px,color:#111;

    classDef prompt fill:#FFFFFF,stroke:#333,stroke-width:2px,color:#111;
    classDef generationModel fill:#FFFFFF,stroke:#333,stroke-width:2px,color:#111;
    classDef output fill:#FFFFFF,stroke:#333,stroke-width:2px,color:#111;

    classDef external fill:#F5F5F5,stroke:#333,stroke-width:2px,color:#111;
    classDef api fill:#FFF8E1,stroke:#333,stroke-width:2px,color:#111;
    classDef retrieval fill:#F5F5F5,stroke:#333,stroke-width:2px,stroke-dasharray: 4 2,color:#111;
```

## Tech Stack

Application:
- Symfone
  
Env:
- Docker

Cloud:
- GCP
  
Database:
- ChromaDB(?)
