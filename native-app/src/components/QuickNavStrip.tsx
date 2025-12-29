import { FC } from 'react';
import { ScrollView, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';

export interface QuickNavItem {
  key: string;
  title: string;
  subtitle: string;
}

interface Props {
  items: QuickNavItem[];
  onSelect: (key: string) => void;
  refreshSignal?: number;
}

export const QuickNavStrip: FC<Props> = ({ items, onSelect, refreshSignal }) => {
  return (
    <ScrollView
      key={refreshSignal ?? 'quick-nav'}
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={styles.container}
    >
      {items.map((item, index) => (
        <TouchableOpacity
          key={item.key}
          style={[styles.card, index === 0 && styles.firstCard]}
          onPress={() => onSelect(item.key)}
          activeOpacity={0.85}
        >
          <Text style={styles.cardTitle}>{item.title}</Text>
          <Text style={styles.cardSubtitle}>{item.subtitle}</Text>
        </TouchableOpacity>
      ))}
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    paddingBottom: spacing.sm,
    paddingRight: spacing.sm,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: spacing.md,
    width: 160,
    shadowColor: '#000',
    shadowOpacity: 0.08,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
    marginRight: spacing.sm,
  },
  firstCard: {
    marginLeft: spacing.sm,
  },
  cardTitle: {
    fontWeight: '700',
    marginBottom: 4,
  },
  cardSubtitle: {
    color: palette.muted,
    fontSize: 12,
  },
});
