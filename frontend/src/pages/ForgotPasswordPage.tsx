import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../lib/api';
import styles from './AuthForm.module.css';

export function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [sent, setSent] = useState(false);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.post('/api/forgot-password', { email });
      setSent(true);
    } finally {
      setSubmitting(false);
    }
  }

  if (sent) {
    return (
      <div className={styles.wrapper}>
        <div className={styles.card}>
          <h1 className={styles.title}>Mot de passe oublié</h1>
          <p>Si un compte existe avec cet email, un lien de réinitialisation vient de vous être envoyé.</p>
          <div className={styles.footer}>
            <Link to="/login">Retour à la connexion</Link>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className={styles.wrapper}>
      <form className={styles.card} onSubmit={handleSubmit}>
        <h1 className={styles.title}>Mot de passe oublié</h1>
        <p>Entrez votre email, nous vous enverrons un lien pour réinitialiser votre mot de passe.</p>
        <div className={styles.field}>
          <label htmlFor="email">Email</label>
          <input
            id="email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            autoFocus
          />
        </div>
        <button className={styles.submit} type="submit" disabled={submitting}>
          {submitting ? 'Envoi…' : 'Envoyer le lien'}
        </button>
        <div className={styles.footer}>
          <Link to="/login">Retour à la connexion</Link>
        </div>
      </form>
    </div>
  );
}
