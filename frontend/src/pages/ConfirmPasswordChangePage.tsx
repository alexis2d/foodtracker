import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api, ApiError } from '../lib/api';
import styles from './AuthForm.module.css';

type Status = 'confirming' | 'success' | 'error';

export function ConfirmPasswordChangePage() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') ?? '';
  const [status, setStatus] = useState<Status>('confirming');
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token) {
      setStatus('error');
      setError('Lien invalide.');
      return;
    }

    api
      .post('/api/confirm-password-change', { token })
      .then(() => setStatus('success'))
      .catch((err) => {
        setStatus('error');
        setError(err instanceof ApiError ? err.message : 'Une erreur est survenue');
      });
  }, [token]);

  return (
    <div className={styles.wrapper}>
      <div className={styles.card}>
        <h1 className={styles.title}>Changement de mot de passe</h1>
        {status === 'confirming' && <p>Confirmation en cours…</p>}
        {status === 'success' && (
          <>
            <p>Votre mot de passe a été changé. Vous pouvez maintenant vous connecter avec le nouveau.</p>
            <div className={styles.footer}>
              <Link to="/login">Aller à la connexion</Link>
            </div>
          </>
        )}
        {status === 'error' && <div className={styles.error}>{error}</div>}
      </div>
    </div>
  );
}
