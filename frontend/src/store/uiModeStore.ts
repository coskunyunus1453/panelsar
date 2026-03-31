import { create } from 'zustand'
import { persist } from 'zustand/middleware'

export type UiMode = 'easy' | 'advanced'

interface UiModeState {
  mode: UiMode
  onboardingSeen: boolean
  advancedTipsSeen: boolean
  setMode: (mode: UiMode) => void
  markOnboardingSeen: () => void
  markAdvancedTipsSeen: () => void
}

export const useUiModeStore = create<UiModeState>()(
  persist(
    (set) => ({
      mode: 'easy',
      onboardingSeen: false,
      advancedTipsSeen: false,
      setMode: (mode) => set({ mode }),
      markOnboardingSeen: () => set({ onboardingSeen: true }),
      markAdvancedTipsSeen: () => set({ advancedTipsSeen: true }),
    }),
    {
      name: 'panelsar-ui-mode',
    },
  ),
)

