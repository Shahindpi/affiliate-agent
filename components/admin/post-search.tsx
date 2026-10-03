"use client";

import { Search } from "lucide-react";
import { Input } from "@/components/ui/input";

import {
  useAppDispatch,
  useAppSelector,
} from "@/store/hooks";

import { setSearch } from "@/store/slices/postFiltersSlice";

export default function PostSearch() {
  const dispatch = useAppDispatch();

  const search = useAppSelector(
    (state) => state.postFilters.search
  );

  return (
    <div className="relative w-full md:w-80">
      <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />

      <Input
        placeholder="Search posts..."
        className="pl-10"
        value={search}
        onChange={(e) =>
          dispatch(setSearch(e.target.value))
        }
      />
    </div>
  );
}