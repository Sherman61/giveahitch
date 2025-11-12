import { useState } from 'react';
import { View, StyleSheet } from 'react-native';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { HomeScreen } from './src/screens/HomeScreen';
import { RidesScreen } from './src/screens/RidesScreen';
import { LoginScreen } from './src/screens/LoginScreen';
import { TabBar, TabItem } from './src/components/TabBar';
import { AuthResponse } from './src/api/auth';

type TabKey = 'rides' | 'notifications' | 'login';

const tabs: TabItem[] = [
  { key: 'rides', label: 'Rides' },
  { key: 'notifications', label: 'Alerts' },
  { key: 'login', label: 'Login' },
];

export default function App() {
  const [activeTab, setActiveTab] = useState<TabKey>('rides');
  const [auth, setAuth] = useState<AuthResponse | null>(null);

  const handleLoginSuccess = (data: AuthResponse) => {
    setAuth(data);
    setActiveTab('rides');
  };

  const handleLogout = () => {
    setAuth(null);
    setActiveTab('login');
  };

  const resolvedTabs = tabs.map((tab) =>
    tab.key === 'login' ? { ...tab, label: auth ? 'Account' : 'Login' } : tab,
  );

  return (
    <SafeAreaProvider>
      <StatusBar style="dark" />
      <View style={styles.root}>
        <View style={styles.content}>
          {activeTab === 'rides' && (
            <RidesScreen user={auth?.user} onRequestLogin={() => setActiveTab('login')} />
          )}
          {activeTab === 'notifications' && <HomeScreen />}
          {activeTab === 'login' && (
            <LoginScreen currentUser={auth?.user ?? null} onLoginSuccess={handleLoginSuccess} onLogout={handleLogout} />
          )}
        </View>
        <TabBar tabs={resolvedTabs} activeKey={activeTab} onChange={(key) => setActiveTab(key as TabKey)} />
      </View>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
  },
  content: {
    flex: 1,
  },
});
