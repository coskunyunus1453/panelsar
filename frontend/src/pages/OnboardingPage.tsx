import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useMutation } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import { Globe, Sparkles } from 'lucide-react'
import toast from 'react-hot-toast'

export default function OnboardingPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const updateUser = useAuthStore((s) => s.updateUser)
  const whiteLabel = useAuthStore((s) => s.whiteLabel)
  const [step, setStep] = useState(0)

  const completeM = useMutation({
    mutationFn: async () => (await api.post('/user/onboarding/complete')).data as { user: { onboarding_completed_at: string | null } },
    onSuccess: (data) => {
      updateUser({ onboarding_completed_at: data.user.onboarding_completed_at ?? new Date().toISOString() })
      toast.success(t('onboarding.done'))
      navigate('/dashboard', { replace: true })
    },
    onError: () => toast.error(t('common.error')),
  })

  const customHtml = whiteLabel?.onboarding_html?.trim()

  return (
    <div className="mx-auto max-w-2xl py-8 px-4">
      <div className="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-panel-card p-8 shadow-sm">
        {step === 0 && (
          <div className="space-y-4">
            <div className="flex items-center gap-3 text-primary-600 dark:text-primary-400">
              <Sparkles className="h-8 w-8" />
              <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">{t('onboarding.welcome_title')}</h1>
            </div>
            {customHtml ? (
              <div
                className="prose prose-sm dark:prose-invert max-w-none text-gray-700 dark:text-gray-300"
                dangerouslySetInnerHTML={{ __html: customHtml }}
              />
            ) : (
              <p className="text-gray-600 dark:text-gray-400">{t('onboarding.welcome_body')}</p>
            )}
            <button type="button" className="btn-primary" onClick={() => setStep(1)}>
              {t('onboarding.next')}
            </button>
          </div>
        )}

        {step === 1 && (
          <div className="space-y-4">
            <div className="flex items-center gap-3 text-primary-600 dark:text-primary-400">
              <Globe className="h-8 w-8" />
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white">{t('onboarding.domains_title')}</h2>
            </div>
            <p className="text-gray-600 dark:text-gray-400">{t('onboarding.domains_body')}</p>
            <div className="flex flex-wrap gap-2">
              <button type="button" className="btn-secondary" onClick={() => navigate('/domains')}>
                {t('onboarding.open_domains')}
              </button>
              <button
                type="button"
                className="btn-primary"
                disabled={completeM.isPending}
                onClick={() => completeM.mutate()}
              >
                {completeM.isPending ? t('common.loading') : t('onboarding.finish')}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
