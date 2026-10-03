"use client";

import {
  useAppDispatch,
  useAppSelector,
} from "@/store/hooks";

import { setPage } from "@/store/slices/postFiltersSlice";

interface Props {
  current: number;
  last: number;
}

export default function TablePagination({
  current,
  last,
}: Props) {
  const dispatch = useAppDispatch();

  const page = useAppSelector(
    (state) => state.postFilters.page
  );

  return (
    <div className="flex items-center justify-between mt-6">
      <button
        className="border rounded-lg px-4 py-2 disabled:opacity-40"
        disabled={page <= 1}
        onClick={() => dispatch(setPage(page - 1))}
      >
        Previous
      </button>

      <p className="text-sm text-muted-foreground">
        Page {current} of {last}
      </p>

      <button
        className="border rounded-lg px-4 py-2 disabled:opacity-40"
        disabled={page >= last}
        onClick={() => dispatch(setPage(page + 1))}
      >
        Next
      </button>
    </div>
  );
}