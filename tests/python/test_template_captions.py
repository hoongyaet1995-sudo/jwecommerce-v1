import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'resources/marketing-renderer/product-template'))
from captions import segments, ass_time


class CaptionSegmentsTest(unittest.TestCase):
    def aligned(self, text, limit=22):
        return segments(list(text), [i*.04 for i in range(len(text))], [(i+1)*.04 for i in range(len(text))], limit)

    def test_english_words_are_never_cut_and_every_character_is_retained(self):
        text = 'Settle into an adjustable over-ear design with memory-foam cushions.'
        cues = self.aligned(text)
        self.assertEqual(text.split(), ' '.join(c['text'] for c in cues).split())
        self.assertTrue(all(c['start'] < c['end'] for c in cues))
        self.assertTrue(all(a['end'] <= b['start'] for a,b in zip(cues, cues[1:])))
        self.assertEqual(cues[0]['start'], 0)
        self.assertAlmostEqual(cues[-1]['end'], len(text)*.04)

    def test_chinese_and_mixed_script_keeps_punctuation_and_content(self):
        for text in ['轻松一点，喝一杯茶。慢慢享受！', '认识 Orbit Pro，看看 Bluetooth 5.3。', "It's a memory-foam design."]:
            cues=self.aligned(text,12)
            self.assertEqual(''.join(text.split()), ''.join(''.join(c['text'].split()) for c in cues))

    def test_long_words_are_not_silently_truncated(self):
        word='a'*32
        self.assertIn(word, [c['text'] for c in self.aligned('Look at '+word+' now.')])
        with self.assertRaisesRegex(ValueError,'caption_word_too_long'):
            self.aligned('a'*60)

    def test_invalid_timestamps_fail_before_subtitle_output(self):
        for starts,ends in [([0,float('nan')],[.1,.2]),([.2,.1],[.3,.4]),([0,.1],[.1,.05])]:
            with self.assertRaisesRegex(ValueError,'caption_alignment_invalid'):
                segments(list('ab'),starts,ends,22)

    def test_ass_centisecond_carry_is_monotonic(self):
        self.assertEqual('0:00:13.00',ass_time(12.999))
        self.assertEqual('0:01:00.00',ass_time(59.999))

if __name__ == '__main__': unittest.main()
