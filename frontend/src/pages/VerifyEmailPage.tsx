import { useEffect, useState, type FormEvent } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api, ApiError } from '../lib/api';
import styles from './AuthForm.module.css';

type Status = 'verifying' | 'success' | 'error';

export function VerifyEmailPage() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') ?? '';
  const [status, setStatus] = useState<Status>('verifying');
  const [error, setError] = useState<string | null>(null);

  const [resendEmail, setResendEmail] = useState('');
  const [resendSent, setResendSent] = useState(false);
  const [resending, setResending] = useState(false);

  useEffect(() => {
    if (!token) {
      setStatus('error');
      setError('Lien invalide.');
      return;
    }

    api
      .post('/api/verify-email', { token })
      .then(() => setStatus('success'))
      .catch((err) => {
        setStatus('error');
        setError(err instanceof ApiError ? err.message : 'Une erreur est survenue');
      });
  }, [token]);

  async function handleResend(e: FormEvent) {
    e.preventDefault();
    setResending(true);
    try {
      await api.post('/api/resend-verification', { email: resendEmail });
      setResendSent(true);
    } finally {
      setResending(false);
    }
  }

  return (
    <div className={styles.wrapper}>
      <div className={styles.card}>
        <h1 className={styles.title}>Activation du compte</h1>
        {status === 'verifying' && <p>Vérification en cours…</p>}
        {status === 'success' && (
          <>
            <p>Votre compte est activé, vous pouvez maintenant vous connecter.</p>
            <div className={styles.footer}>
              <Link to="/login">Aller à la connexion</Link>
            </div>
          </>
        )}
        {status === 'error' && (
          <>
            <div className={styles.error}>{error}</div>
            {resendSent ? (
              <p>Si un compte existe pour cet email, un nouveau lien vient d'être envoyé.</p>
            ) : (
              <form onSubmit={handleResend}>
                <p>Le lien est peut-être expiré. Recevez-en un nouveau :</p>
                <div className={styles.field}>
                  <label htmlFor="resend-email">Email</label>
                  <input
                    id="resend-email"
                    type="email"
                    value={resendEmail}
                    onChange={(e) => setResendEmail(e.target.value)}
                    required
                  />
                </div>
                <button className={styles.submit} type="submit" disabled={resending}>
                  {resending ? 'Envoi…' : "Renvoyer l'email d'activation"}
                </button>
              </form>
            )}
          </>
        )}
      </div>
    </div>
  );
}
