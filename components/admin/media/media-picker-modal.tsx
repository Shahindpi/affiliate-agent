"use client";

import { useState } from "react";
import Image from "next/image";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ImagePlus } from "lucide-react";
import { toast } from "sonner";

import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";

import { getMedia, uploadMedia } from "@/services/media";
import { MediaItem } from "@/types/media";

interface Props {
  open: boolean;
  onClose: () => void;
  onSelect: (image: MediaItem) => void;
}

export default function MediaPickerModal({ open, onClose, onSelect }: Props) {
  const queryClient = useQueryClient();

  const [selectedFile, setSelectedFile] = useState<File | null>(null);

  const { data: media = [], isLoading } = useQuery({
    queryKey: ["media"],
    queryFn: getMedia,
    enabled: open,
    staleTime: 1000 * 60 * 10,
    gcTime: 1000 * 60 * 30,
  });

  const upload = useMutation({
    mutationFn: uploadMedia,

    onSuccess: (image) => {
      toast.success("Image uploaded successfully.");

      // Safe cache update
      queryClient.setQueryData(["media"], (old: unknown) => {
        const items = Array.isArray(old) ? old : [];

        return [
          image,
          ...items.filter((item: MediaItem) => item.path !== image.path),
        ];
      });

      setSelectedFile(null);

      onSelect(image);

      // Close modal
      onClose();
    },

    onError: (error: { response?: { data?: { message?: string } } }) => {
      toast.error(error?.response?.data?.message ?? "Failed to upload image.");
    },
  });

  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        if (!nextOpen) {
          setSelectedFile(null);
          onClose();
        }
      }}
    >
      <DialogContent className="max-w-5xl">
        <DialogHeader>
          <DialogTitle>Media Library</DialogTitle>
        </DialogHeader>

        {/* Upload Area */}
        <div className="rounded-2xl border-2 border-dashed border-primary/40 bg-primary/5 p-8">
          <label
            htmlFor="media-upload"
            className="flex cursor-pointer flex-col items-center justify-center gap-3 text-center"
          >
            <div className="rounded-full bg-primary/10 p-4">
              <ImagePlus className="h-10 w-10 text-primary" />
            </div>

            <div>
              <p className="font-medium text-primary">
                Click to upload an image
              </p>

              <p className="text-sm text-muted-foreground">
                PNG, JPG, JPEG or WebP up to 5 MB.
              </p>
            </div>

            {selectedFile && (
              <div className="rounded-lg bg-background px-3 py-2 text-sm font-medium shadow">
                {selectedFile.name}
              </div>
            )}

            <input
              id="media-upload"
              type="file"
              accept="image/*"
              className="hidden"
              onChange={(e) => setSelectedFile(e.target.files?.[0] ?? null)}
            />
          </label>

          <Button
            type="button"
            className="mt-6 w-full cursor-pointer"
            disabled={!selectedFile || upload.isPending}
            onClick={() => {
              if (selectedFile) {
                upload.mutate(selectedFile);
              }
            }}
          >
            {upload.isPending ? "Uploading Image..." : "Upload Image"}
          </Button>
        </div>

        {/* Media Grid */}
        {isLoading ? (
          <div className="grid grid-cols-2 gap-4 py-8 md:grid-cols-4">
            {Array.from({ length: 8 }).map((_, index) => (
              <div
                key={index}
                className="aspect-square animate-pulse rounded-xl bg-muted"
              />
            ))}
          </div>
        ) : (
          <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
            {media.length > 0 ? (
              media.map((image, index) => (
                <button
                  key={`${image.path}-${index}`}
                  type="button"
                  className="cursor-pointer overflow-hidden rounded-xl border transition hover:border-primary"
                  onClick={() => {
                    onSelect(image);
                    onClose();
                  }}
                >
                  <Image
                    unoptimized
                    width={320}
                    height={320}
                    src={image.url}
                    alt={image.name}
                    className="aspect-square w-full object-cover"
                  />
                </button>
              ))
            ) : (
              <div className="col-span-full py-10 text-center text-sm text-muted-foreground">
                No images found.
              </div>
            )}
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
