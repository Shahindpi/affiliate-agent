import { createSlice, PayloadAction } from "@reduxjs/toolkit";

interface PostFilterState {
  search: string;

  status: string;

  page: number;
  per_page: 10 | 20 | 50 | "all";
}

const initialState: PostFilterState = {
  search: "",

  status: "",

  page: 1,
  per_page: 20,
};

const slice = createSlice({
  name: "postFilters",

  initialState,

  reducers: {
    setSearch(state, action: PayloadAction<string>) {
      state.search = action.payload;

      state.page = 1;
    },

    setStatus(state, action: PayloadAction<string>) {
      state.status = action.payload;

      state.page = 1;
    },

    setPageSize(state, action: PayloadAction<10 | 20 | 50 | "all">) { state.per_page = action.payload; state.page = 1; },
    setPage(state, action: PayloadAction<number>) {
      state.page = action.payload;
    },

    resetFilters() {
      return initialState;
    },
  },
});

export const {
  setSearch,
  setStatus,
  setPage,
  setPageSize,
  resetFilters,
} = slice.actions;

export default slice.reducer;