import { useEffect } from 'react';
import { trackScreenView } from '@/lib/analytics';

export function useScreenAnalytics(screenName: string, screenClass?: string) {
  useEffect(() => {
    void trackScreenView(screenName, screenClass);
  }, [screenClass, screenName]);
}
