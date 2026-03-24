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
            activeOpacity={0.82}
            hitSlop={{ top: 8, bottom: 8, left: 6, right: 6 }}
            accessibilityRole="button"
            accessibilityState={{ selected: isActive }}
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
    borderColor: '#dbe3ea',
    paddingHorizontal: spacing.sm,
    paddingTop: spacing.sm,
    paddingBottom: spacing.md,
    zIndex: 10,
    elevation: 10,
  },
  tab: {
    flex: 1,
    minHeight: 56,
    paddingVertical: spacing.md,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 10,
  },
  activeTab: {
    backgroundColor: '#e8f1fb',
  },
  label: {
    color: palette.muted,
    fontWeight: '500',
    fontSize: 12,
  },
  activeLabel: {
    color: palette.primary,
    fontWeight: '700',
  },
});
