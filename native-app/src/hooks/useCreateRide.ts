import { useState, useCallback } from 'react';
import { createRide, CreateRidePayload } from '@/api/rides';

export function useCreateRide() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  const submitAsync = useCallback(
    async (payload: CreateRidePayload) => {
      setLoading(true);
      setError(null);
      setSuccessMessage(null);
      try {
        const response = await createRide(payload);
      setSuccessMessage('Ride posted successfully.');
      return response;
      } catch (err) {
        const message = err instanceof Error ? err.message : 'Failed to create ride';
        setError(message);
        throw err;
      } finally {
        setLoading(false);
      }
    },
    [],
  );

  return {
    createRideAsync: submitAsync,
    loading,
    error,
    successMessage,
  };
}
