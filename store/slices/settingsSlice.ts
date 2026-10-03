import { createSlice, PayloadAction } from "@reduxjs/toolkit";

import { SiteSettings } from "@/types/setting";

interface SettingsState {
  settings: SiteSettings | null;
}

const initialState: SettingsState = {
  settings: null,
};

const settingsSlice = createSlice({
  name: "settings",

  initialState,

  reducers: {
    setSettings(
      state,
      action: PayloadAction<SiteSettings>
    ) {
      state.settings = action.payload;
    },
  },
});

export const { setSettings } = settingsSlice.actions;

export default settingsSlice.reducer;