import axios from 'axios';

interface ChunkedUploadOptions {
    file: File;
    url: string;
    model: string;
    modelId: string;
    collection?: string;
    chunkSize?: number;
    onProgress?: (progress: number) => void;
    onComplete?: (response: any) => void;
    onError?: (error: any) => void;
}

interface ChunkedUploadResult {
    id: string;
    url: string;
    type: string;
    original_filename: string;
}

const DEFAULT_CHUNK_SIZE = 5 * 1024 * 1024; // 5MB chunks

export async function uploadChunked(options: ChunkedUploadOptions): Promise<ChunkedUploadResult> {
    const {
        file,
        url,
        model,
        modelId,
        collection = 'default',
        chunkSize = DEFAULT_CHUNK_SIZE,
        onProgress,
        onComplete,
        onError,
    } = options;

    const totalSize = file.size;
    const totalChunks = Math.ceil(totalSize / chunkSize);
    let uploadedBytes = 0;

    try {
        for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
            const start = chunkIndex * chunkSize;
            const end = Math.min(start + chunkSize, totalSize);
            const chunk = file.slice(start, end);

            const response = await axios.post(url, chunk, {
                headers: {
                    'Content-Type': 'application/octet-stream',
                    'Content-Range': `bytes ${start}-${end - 1}/${totalSize}`,
                    'X-Model': model,
                    'X-Model-Id': modelId,
                    'X-Collection': collection,
                    'X-File-Name': file.name,
                },
            });

            uploadedBytes = end;
            const progress = Math.round((uploadedBytes / totalSize) * 100);
            onProgress?.(progress);

            if (response.data.done) {
                onComplete?.(response.data);
                return response.data;
            }
        }

        throw new Error('Upload did not complete');
    } catch (error) {
        onError?.(error);
        throw error;
    }
}

// Threshold for when to use chunked upload (10MB)
const CHUNKED_UPLOAD_THRESHOLD = 10 * 1024 * 1024;

export function shouldUseChunkedUpload(file: File): boolean {
    return file.size > CHUNKED_UPLOAD_THRESHOLD;
}
