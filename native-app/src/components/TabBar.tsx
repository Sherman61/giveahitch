import { FC } from 'react';
import { View, TouchableOpacity, Text, StyleSheet } from 'react-native';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';

export interface TabItem {
  key: string;
  label: string;
}

interface Props {
  tabs: TabItem[];
  activeKey: string;
  onChange: (key: string) => void;
}

export const TabBar: FC<Props> = ({ tabs, activeKey, onChange }) => {
  return (
    <View style={styles.container}>
      {tabs.map((tab) => {
        const isActive = tab.key === activeKey;
        return (
          <TouchableOpacity
            key={tab.key}
            style={[styles.tab, isActive && styles.activeTab]}
            onPress={() => onChange(tab.key)}
          >
            <Text style={[styles.label, isActive && styles.activeLabel]}>{tab.label}</Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    backgroundColor: palette.surface,
    borderTopWidth: 1,
    borderColor: '#e9ecef',
  },
  tab: {
    flex: 1,
    paddingVertical: spacing.md,
    alignItems: 'center',
  },
  activeTab: {
    borderBottomWidth: 3,
    borderBottomColor: palette.primary,
  },
  label: {
    color: palette.muted,
    fontWeight: '500',
  },
  activeLabel: {
    color: palette.primary,
  },
});
