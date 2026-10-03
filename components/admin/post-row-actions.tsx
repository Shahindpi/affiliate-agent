"use client";
import { routes } from "@/lib/routes";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { MoreHorizontal, Pencil, Eye, Trash2 } from "lucide-react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
} from "@/components/ui/dropdown-menu";

import DeletePostDialog from "./delete-post-dialog";
import { deletePost } from "@/services/posts";

interface Props {
  id: number;
  slug: string;
  title: string;
}

export default function PostRowActions({
  id,
  slug,
  title,
}: Props) {
  const router = useRouter();
  const queryClient = useQueryClient();

  const [dialogOpen, setDialogOpen] = useState(false);

  const mutation = useMutation({
    mutationFn: () => deletePost(id),

    onSuccess: () => {
      toast.success("Post deleted successfully.");

      queryClient.invalidateQueries({
        queryKey: ["posts"],
      });

      setDialogOpen(false);
    },

    onError: () => {
      toast.error("Failed to delete post.");
    },
  });

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger aria-label={`Actions for ${title}`} className="flex h-8 w-8 items-center justify-center rounded-md hover:bg-muted">
          <MoreHorizontal className="h-4 w-4" />
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end">
          <DropdownMenuItem
            onClick={() => router.push(routes.admin.posts.edit(id))}
          >
            <Pencil className="h-4 w-4" />
            Edit
          </DropdownMenuItem>

          <DropdownMenuItem
            onClick={() => window.open(`/posts/${slug}`, "_blank", "noopener,noreferrer")}
          >
            <Eye className="h-4 w-4" />
            View Live
          </DropdownMenuItem>

          <DropdownMenuItem
            variant="destructive"
            onClick={() => setDialogOpen(true)}
          >
            <Trash2 className="h-4 w-4" />
            Delete
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <DeletePostDialog
        open={dialogOpen}
        loading={mutation.isPending}
        title={title}
        onClose={() => setDialogOpen(false)}
        onDelete={() => mutation.mutate()}
      />
    </>
  );
}
