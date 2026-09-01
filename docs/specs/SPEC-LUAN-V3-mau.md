# BÀI MẪU LUAN-V3 (demo giọng cổ — anh Tuyền duyệt mắt, chưa code)

Case dựng sẵn theo router V2: khách ngồi tab **Tài lộc**, gõ "người ấy nghĩ gì về em".
Router → `duyen` (nhánh cross-tab). Quẻ: id31 **Trạch Sơn Hàm** 咸, hào 4 động (case 1 — luật chọn lời: hào từ Cửu Tứ, BianRule.php:57).
Mốc dữ liệu trích thật: quẻ từ 2896, hào 4 2935, đại ý 2878 (hexagrams.json).

---

[Thời] — Thì của chuyện

Tiền với tình, xét cho cùng, chung một mối lo: lòng có yên thì tính toán mới đúng. Quẻ của phiên này là Hàm — cảm ứng. Đại ý nói rất gọn: chạm đúng chỗ thì im lặng cũng hiểu nhau. Vậy thì của chuyện nhà mình là thì hai người đã nhớ tin nhau mà chưa ai dám nói trước — nước đã đến mép, mới chỉ gợn.

[Vị] — Xét cho ra nhẽ

Hào bốn là hào động, luật Biện quẻ lấy lời hào này làm chủ:

九四：貞吉悔亡，憧憧往來，朋從爾思。
Cửu tứ: trinh cát, hối vong; đồng đồng vãng lai, bằng tòng nhĩ tư.
Giữ điều đúng thì tốt, ăn năn mất dần; chỉ vì trong lòng đi lại chộn rộn, nên kẻ quanh mình cũng theo cái nghĩ ấy mà thôi.

Chữ "đồng đồng" là chộn rộn: đêm nghĩ một đường, ngày đi một nẻo. Người ta chưa mở lời, mình đừng đoán thay; cứ giữ cho đúng phần mình — thương thì hỏi han thật thà, đừng đem tin nhắn ra mà đếm. Hào từ rành rành một lẽ: cái chộn rộn trong lòng mình, sửa nó trước, rồi chuyện kia tự thông. Bài này không hứa cho em biết người ta nghĩ gì — chỉ soi cho em thấy mình đang nghĩ gì.

[Ứng] — Liệu mà làm

- Ba bữa tới: mỗi ngày trả lời người ấy một câu cho ra đầu ra đũa, không thả biểu tượng cho qua chuyện.
- Năm hôm nữa: hẹn hẳn một buổi, nói rõ giờ rõ chốn; thư lửng thì đừng mong chuyện rõ.
- Mười lăm phút mỗi tối: cất điện thoại, viết ra điều mình thật sự muốn nói; viết xong mà vẫn muốn gặp, mai hãy gặp.

Lời quẻ là cái đèn soi ngõ, còn bước đi là việc của chân mình; bài này chỉ mang tính tham khảo giải trí về văn hoá.

---

Đếm từ (split whitespace, kể cả 3 dòng trích dẫn): ~300 từ → nằm trong 200–420.
Wordguard thủ công: không chứa 8 mẫu cấm (BANNED_PATTERNS Wordguard.php:14-17) — không "hòa giải", "cúng", "giải hạn", "bùa", "thay đổi vận mệnh", "tâm linh", "thỉnh", "cốt".
Quy tắc Hán→quốc âm (hard rule V3): dòng 4 ký tự Hán → dòng ngay dưới mở "Cửu tứ:" (quốc âm) → dòng dưới nữa = nghĩa. QA regex được.
