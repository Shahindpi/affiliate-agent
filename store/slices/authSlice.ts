import { createSlice, PayloadAction } from "@reduxjs/toolkit";

import { User } from "@/types/user";

interface AuthState {
  token: string | null;

  user: User | null;

  authenticated: boolean;

  loading: boolean;
}

const initialState: AuthState = {
  token: null,

  user: null,

  authenticated: false,

  loading: true,
};

const authSlice = createSlice({
  name: "auth",

  initialState,

  reducers: {
    setCredentials: (
      state,
      action: PayloadAction<{
        token: string;
        user: User;
      }>
    ) => {
      state.token = action.payload.token;

      state.user = action.payload.user;

      state.authenticated = true;

      state.loading = false;
    },

    setUser: (state, action: PayloadAction<User>) => {
      state.user = action.payload;

      state.authenticated = true;
    },

    logout: (state) => {
      state.token = null;

      state.user = null;

      state.authenticated = false;

      state.loading = false;
    },

    setLoading: (state, action: PayloadAction<boolean>) => {
      state.loading = action.payload;
    },
  },
});

export const {
  setCredentials,
  setUser,
  logout,
  setLoading,
} = authSlice.actions;

export default authSlice.reducer;