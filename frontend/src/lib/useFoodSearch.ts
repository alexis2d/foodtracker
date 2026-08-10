import { useState, useEffect, useRef } from 'react';
import { api } from './api';
import type { Food } from './types';

export function useFoodSearch() {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<Food[]>([]);
  const [loading, setLoading] = useState(false);
  const debounceRef = useRef<number | undefined>(undefined);

  useEffect(() => {
    if (query.trim().length < 2) {
      setResults([]);
      return;
    }
    window.clearTimeout(debounceRef.current);
    debounceRef.current = window.setTimeout(() => {
      setLoading(true);
      api
        .get<{ results: Food[] }>(`/api/foods/search?q=${encodeURIComponent(query)}`)
        .then((data) => setResults(data.results))
        .finally(() => setLoading(false));
    }, 300);
    return () => window.clearTimeout(debounceRef.current);
  }, [query]);

  return { query, setQuery, results, loading };
}
