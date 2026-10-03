"use client";

import {
  useAppDispatch,
  useAppSelector,
} from "@/store/hooks";

import { setStatus } from "@/store/slices/postFiltersSlice";

export default function PostStatusFilter() {
  const dispatch = useAppDispatch();

  const status = useAppSelector(
    (state) => state.postFilters.status
  );

  return (
    <select
      className="h-10 rounded-md border px-3 bg-background text-sm"
      value={status}
      onChange={(e) =>
        dispatch(setStatus(e.target.value))
      }
    >
      <option value="">All Status</option>
      <option value="published">Published</option>
      <option value="draft">Draft</option>
    </select>
  );
}