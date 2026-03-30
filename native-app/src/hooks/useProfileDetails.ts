import { useEffect, useState } from 'react';
import { fetchProfileDetails, ProfileDetails } from '@/api/profile';

export function useProfileDetails(enabled: boolean) {
  const [profile, setProfile] = useState<ProfileDetails | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!enabled) {
      setProfile(null);
      return;
    }
    setLoading(true);
    fetchProfileDetails()
      .then((data) => {
        setProfile(data);
        setError(null);
      })
      .catch((err) => {
        setError(err instanceof Error ? err.message : 'Unable to load profile');
      })
      .finally(() => setLoading(false));
  }, [enabled]);

  return { profile, loading, error };
}
