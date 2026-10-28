#!/usr/bin/env python3
"""Generate complete WapApp DPDP platform findings PDF."""

from datetime import date
from pathlib import Path

from fpdf import FPDF
from fpdf.enums import XPos, YPos

OUT = Path(__file__).resolve().parent / "WapApp_DPDP_Platform_Assessment.pdf"


class AssessmentPDF(FPDF):
    def header(self) -> None:
        if self.page_no() == 1:
            return
        self.set_font("Helvetica", "B", 9)
        self.set_text_color(40, 60, 80)
        self.cell(0, 6, "WapApp - DPDP Platform Findings")
        self.ln(2)
        self.set_draw_color(200, 210, 220)
        self.line(self.l_margin, self.get_y(), self.w - self.r_margin, self.get_y())
        self.ln(6)

    def footer(self) -> None:
        self.set_y(-14)
        self.set_font("Helvetica", "", 8)
        self.set_text_color(120, 120, 120)
        self.cell(0, 5, "Confidential | Not legal advice | Based on product findings")
        self.cell(0, 5, f"Page {self.page_no()}", align="R")

    def h1(self, text: str) -> None:
        self.set_x(self.l_margin)
        self.set_font("Helvetica", "B", 15)
        self.set_text_color(15, 50, 80)
        self.multi_cell(self.epw, 8, text)
        self.set_x(self.l_margin)
        self.ln(2)

    def h2(self, text: str) -> None:
        self.ln(2)
        self.set_x(self.l_margin)
        self.set_font("Helvetica", "B", 11)
        self.set_text_color(20, 70, 110)
        self.multi_cell(self.epw, 6.5, text)
        self.set_x(self.l_margin)
        self.ln(1)

    def h3(self, text: str) -> None:
        self.ln(1)
        self.set_x(self.l_margin)
        self.set_font("Helvetica", "B", 10)
        self.set_text_color(30, 30, 30)
        self.multi_cell(self.epw, 6, text)
        self.set_x(self.l_margin)
        self.ln(0.5)

    def body(self, text: str) -> None:
        self.set_x(self.l_margin)
        self.set_font("Helvetica", "", 10)
        self.set_text_color(35, 35, 35)
        self.multi_cell(self.epw, 5.4, text)
        self.set_x(self.l_margin)
        self.ln(1.2)

    def bullet(self, text: str) -> None:
        self.set_x(self.l_margin)
        self.set_font("Helvetica", "", 10)
        self.set_text_color(35, 35, 35)
        self.cell(5, 5.4, "-")
        self.multi_cell(self.epw - 5, 5.4, text)
        self.set_x(self.l_margin)
        self.ln(0.4)

    def callout(self, title: str, text: str) -> None:
        self.ln(1)
        w = self.epw
        self.set_x(self.l_margin)
        self.set_fill_color(240, 246, 252)
        self.set_font("Helvetica", "B", 10)
        self.set_text_color(15, 50, 80)
        self.multi_cell(w, 6, title, fill=True)
        self.set_x(self.l_margin)
        self.set_font("Helvetica", "", 10)
        self.set_text_color(35, 35, 35)
        self.multi_cell(w, 5.4, text, fill=True)
        self.set_x(self.l_margin)
        self.ln(2)

    def table(self, headers: list[str], rows: list[tuple[str, ...]], widths: list[float]) -> None:
        self.set_x(self.l_margin)
        self.set_font("Helvetica", "B", 8)
        self.set_fill_color(220, 230, 240)
        for i, h in enumerate(headers):
            self.cell(widths[i], 7, h, border=1, fill=True)
        self.ln()
        self.set_font("Helvetica", "", 8)
        for r in rows:
            self.set_x(self.l_margin)
            # simple single-line cells; truncate long text
            for i, cell in enumerate(r):
                txt = cell if len(cell) <= 55 else cell[:52] + "..."
                self.cell(widths[i], 6.5, txt, border=1)
            self.ln()
        self.ln(3)
        self.set_x(self.l_margin)


def build() -> Path:
    pdf = AssessmentPDF(format="A4")
    pdf.set_auto_page_break(auto=True, margin=18)
    pdf.add_page()

    # Cover
    pdf.set_xy(pdf.l_margin, 36)
    pdf.set_font("Helvetica", "B", 22)
    pdf.set_text_color(15, 50, 80)
    pdf.cell(0, 12, "WapApp Platform Assessment", new_x=XPos.LMARGIN, new_y=YPos.NEXT)

    pdf.set_x(pdf.l_margin)
    pdf.set_font("Helvetica", "B", 13)
    pdf.set_text_color(40, 90, 130)
    pdf.multi_cell(pdf.epw, 8, "Digital Personal Data Protection (DPDP) - Complete Findings")

    y = pdf.get_y() + 4
    pdf.set_draw_color(40, 90, 130)
    pdf.line(pdf.l_margin, y, pdf.l_margin + 90, y)
    pdf.set_xy(pdf.l_margin, y + 10)

    pdf.set_font("Helvetica", "", 11)
    pdf.set_text_color(50, 50, 50)
    for line in [
        f"Date: {date.today().strftime('%d %B %Y')}",
        "Document: Product and architecture findings for WapApp 2.0",
        "Reference laws: Digital Personal Data Protection Act, 2023; DPDP Rules, 2025",
        "Also reviewed: Official bilingual Gazette notification G.S.R. 846(E) (Hindi + English)",
    ]:
        pdf.set_x(pdf.l_margin)
        pdf.multi_cell(pdf.epw, 6.5, line)

    pdf.set_xy(pdf.l_margin, pdf.get_y() + 8)
    pdf.callout(
        "Disclaimer (please read)",
        "This document records product/architecture findings only. It is not a legal opinion, "
        "audit certificate, or confirmation of full DPDP compliance. Penalty figures below are "
        "maximum ceilings from the DPDP Act Schedule; actual penalties (if any) depend on "
        "Data Protection Board inquiry and facts. Final Privacy Policy / Terms wording and "
        "vendor contracts must be reviewed by legal counsel before public use.",
    )

    # 1 Current situation
    pdf.add_page()
    pdf.h1("1. Current situation (as of assessment date)")
    pdf.body(
        "WapApp is a multi-tenant WhatsApp Business messaging platform. It processes personal data "
        "such as phone numbers, names, message content and media to send/receive WhatsApp "
        "communications for customer accounts (tenants)."
    )
    pdf.h3("Overall status")
    pdf.bullet("Product controls: largely present (opt-in, STOP/unsubscribe, isolation, 2FA, purge).")
    pdf.bullet("Public transparency: incomplete (Privacy Policy / Terms need clearer DPDP-oriented disclosure).")
    pdf.bullet("Third-party delivery path: Alibaba CAMS + Meta WhatsApp (data leaves WapApp for delivery).")
    pdf.bullet("Safe public claim today: do NOT claim \"100% DPDP certified\". Prefer transparent controls + disclosure language.")
    pdf.callout(
        "Plain-language status",
        "We are not in a \"zero control\" state. Day-to-day messaging controls exist. "
        "The main open item is website and legal packaging: clearly explain roles, purposes, "
        "Alibaba/Meta processing, cross-border flow, opt-out, retention, and grievance contact. "
        "That gap should be closed to reduce future compliance risk.",
    )

    # 2 Roles
    pdf.h1("2. Roles and responsibilities")
    pdf.h3("2.1 WapApp customers (tenants)")
    pdf.body(
        "Typically the Data Fiduciary for their end users / WhatsApp contacts. They decide why "
        "contacts are messaged and must have a valid basis (usually consent for promotional messaging). "
        "They remain responsible for lawful use of contact lists and WhatsApp/Meta policy compliance."
    )
    pdf.h3("2.2 WapApp / Tekpro (platform)")
    pdf.body(
        "Provides software and infrastructure. Processes account/billing/support data for customers. "
        "Also processes contact and message data to deliver WhatsApp services on customer behalf. "
        "Platform duties include security, purpose limitation for platform processing, and clear notices."
    )
    pdf.h3("2.3 Alibaba Cloud CAMS + Meta WhatsApp")
    pdf.body(
        "Service providers / sub-processors for WhatsApp Business API routing and delivery. "
        "WapApp currently uses Alibaba CAMS (default endpoint region: cams.ap-southeast-1.aliyuncs.com, "
        "typically Singapore) and Meta WhatsApp for message delivery and status webhooks."
    )

    # 3 Product findings
    pdf.h1("3. Existing product controls (findings)")
    pdf.h2("3.1 Consent and messaging controls")
    for b in [
        "Opt-in templates and audience opt-in status tracking for contacts.",
        "Campaign policy confirmation: customer must confirm recipients agreed to receive WhatsApp messages.",
        "Default opt-in style messaging includes STOP-oriented opt-out language.",
        "Signup consent records during registration steps.",
        "WhatsApp / ISV terms acceptance during onboarding.",
    ]:
        pdf.bullet(b)

    pdf.h2("3.2 Withdrawal of consent / opt-out")
    for b in [
        "Inbound STOP / STOP promotions keywords unsubscribe the contact.",
        "START keyword can re-subscribe where applicable.",
        "Public unsubscribe link (token-based) for list/campaign unsubscribe.",
        "Outbound messaging blocked for STOP / unsubscribed contacts.",
        "Inbox UI shows STOP status so agents do not continue messaging opted-out users.",
    ]:
        pdf.bullet(b)

    pdf.h2("3.3 Security and access")
    for b in [
        "Multi-tenant isolation (customer data separated by tenant).",
        "Authentication with two-factor authentication (2FA) support.",
        "Team permission controls for feature access.",
        "Optional phone masking in inbox presentation.",
        "HTTPS / application access controls as part of normal platform security posture.",
    ]:
        pdf.bullet(b)

    pdf.h2("3.4 Retention and deletion")
    for b in [
        "Soft-deleted contact purge after a retention window.",
        "Admin retention views and expired / purge-marked account handling.",
        "Account wipe / purge related operational flows for closed or expired customers.",
        "Some operational notices already reference retention consistent with Privacy Policy.",
    ]:
        pdf.bullet(b)

    # 4 Alibaba
    pdf.h1("4. Alibaba / Meta data flow (detailed finding)")
    pdf.body(
        "Yes - personal data used for WhatsApp messaging goes to Alibaba, and then to Meta, "
        "because that is how delivery works on the current stack. This is expected for a WhatsApp "
        "Business API platform using Alibaba CAMS."
    )
    pdf.h3("4.1 Data that typically crosses")
    for b in [
        "Recipient and business WhatsApp phone numbers",
        "Message / template content and parameters",
        "Media attachments when sent or received",
        "Delivery and status webhook events",
    ]:
        pdf.bullet(b)

    pdf.h3("4.2 Why this is not automatically unlawful")
    pdf.body(
        "DPDP does not prohibit all cross-border processing. Using a cloud BSP/API provider to "
        "deliver WhatsApp messages is normal and often unavoidable. The compliance question is "
        "whether sharing is disclosed, purpose-limited (delivery), and supported by contractual/"
        "security safeguards."
    )
    pdf.h3("4.3 When it becomes a problem")
    for b in [
        "Privacy Policy / Terms hide Alibaba, Meta, or overseas processing.",
        "Customers are not told contact data passes through these providers.",
        "Data is used beyond delivery needs without disclosure.",
        "Vendor safeguards / contracts are unclear or missing.",
    ]:
        pdf.bullet(b)

    pdf.callout(
        "Suggested disclosure wording (concept for legal review)",
        "To deliver WhatsApp messages, we use Alibaba Cloud CAMS (WhatsApp Business API provider; "
        "processing may occur outside India, e.g. Singapore) and Meta Platforms (WhatsApp). "
        "Phone numbers, message content, and related delivery data are shared with them only to "
        "send/receive WhatsApp communications and related delivery status processing.",
    )
    pdf.callout(
        "Key sentence",
        "Data going to Alibaba is not itself the core issue. Hiding that fact is the issue. "
        "Disclose + contract + controls is the safe posture.",
    )

    # 5 Penalties
    pdf.h1("5. Penalties under DPDP Act, 2023 (context)")
    pdf.body(
        "The DPDP Act allows the Data Protection Board of India to impose monetary penalties after "
        "inquiry, where breach of the Act/Rules is significant. Figures below are maximum ceilings "
        "from the Act Schedule (Section 33). They are NOT automatic fines for every gap."
    )
    pdf.table(
        ["Breach type (summary)", "Maximum penalty"],
        [
            ("Failure of reasonable security safeguards (s.8(5))", "Up to Rs 250 crore"),
            ("Failure to notify Board / affected persons of breach (s.8(6))", "Up to Rs 200 crore"),
            ("Children-related obligations (s.9)", "Up to Rs 200 crore"),
            ("Significant Data Fiduciary additional duties (s.10)", "Up to Rs 150 crore"),
            ("Other Act / Rules non-compliance", "Up to Rs 50 crore"),
            ("Data Principal duties (s.15)", "Up to Rs 10,000"),
        ],
        [130, 60],
    )
    pdf.h3("How Board decides amount (high level)")
    for b in [
        "Nature, gravity and duration of the breach",
        "Type of personal data affected",
        "Whether breach is repetitive",
        "Gain made / loss avoided",
        "Mitigation actions and how quickly they were taken",
        "Proportionality and deterrence",
        "Likely impact of the penalty on the person",
    ]:
        pdf.bullet(b)
    pdf.callout(
        "Practical reading for WapApp",
        "Largest penalties are aimed at serious failures (especially security and breach notification). "
        "An incomplete website policy alone does not automatically equal the maximum fine. "
        "However, ignoring disclosure, security, and rights processes increases risk if an inquiry occurs. "
        "Closing documentation gaps now is preventive hygiene.",
    )

    # 6 Gaps
    pdf.h1("6. Gaps identified")
    for b in [
        "Publish a clear Privacy Policy: roles, data categories, purposes, processors, cross-border transfer, retention, rights.",
        "Publish Terms of Use: customer responsibility for lawful consent and WhatsApp/Meta policy compliance.",
        "Publish grievance / privacy contact (email + expected response handling).",
        "Confirm vendor contractual safeguards with Alibaba / Meta (legal ownership).",
        "Ensure signup, billing and footer links point to real, updated policy pages.",
        "Optional later: product workflow for access / correction / erasure requests.",
        "Do not market \"fully DPDP certified\" without formal legal process.",
    ]:
        pdf.bullet(b)

    # 7 Website checklist
    pdf.h1("7. Website disclosure checklist")
    pdf.h2("7.1 Privacy Policy should include")
    for b in [
        "Who we are; platform role vs customer (tenant) role",
        "Categories of personal data (account, WhatsApp business assets, contacts, messages, logs)",
        "Purposes (delivery, campaigns, inbox/chatbot, billing, support, security)",
        "Alibaba CAMS + Meta WhatsApp as processors / service providers",
        "Cross-border / outside-India processing disclosure",
        "Consent, STOP/unsubscribe and related customer obligations",
        "Retention and deletion overview",
        "High-level security measures",
        "How to exercise rights and raise a grievance",
    ]:
        pdf.bullet(b)

    pdf.h2("7.2 Terms of Use should include")
    for b in [
        "Customer must have lawful basis/consent before messaging contacts",
        "No purchased/cold lists that violate WhatsApp or applicable law",
        "Customer remains responsible for end-user communications on their account",
        "Platform may restrict/suspend misuse that risks quality or legal exposure",
    ]:
        pdf.bullet(b)

    pdf.h2("7.3 Recommended site placement")
    for b in [
        "Dedicated Privacy Policy page",
        "Dedicated Terms of Use page",
        "Links from signup, billing, and website footer",
        "Optional short sub-processors note (Alibaba, Meta, payment, email providers as applicable)",
    ]:
        pdf.bullet(b)

    # 8 Readiness
    pdf.h1("8. Readiness snapshot")
    pdf.table(
        ["Area", "Product", "Website / Legal"],
        [
            ("Opt-in / STOP / unsubscribe", "Ready", "Describe in Privacy Policy"),
            ("Tenant isolation / 2FA", "Ready", "High-level security note"),
            ("Retention / purge", "Ready", "Describe retention overview"),
            ("Alibaba / Meta transfer", "Happening", "Must disclose clearly"),
            ("Rights / grievance channel", "Weak", "Must publish process + contact"),
            ("Vendor contracts / DPA", "Legal check", "Confirm and reference safeguards"),
            ("Customer fiduciary duties", "Partial in UI", "Make explicit in Terms"),
            ("Public \"DPDP certified\" claim", "Not supported", "Avoid until legal sign-off"),
        ],
        [58, 32, 100],
    )

    # 9 Risk
    pdf.h1("9. Risk reading (findings-based)")
    pdf.bullet("Not a \"no controls\" situation: messaging consent/opt-out/security building blocks exist.")
    pdf.bullet("Main residual risk: incomplete public disclosure of processors and cross-border delivery path.")
    pdf.bullet("Secondary residual risk: rights/grievance channel not clearly published.")
    pdf.bullet("Highest statutory penalty ceilings exist in law, but apply after Board process and depend on severity.")
    pdf.bullet("Immediate practical mitigation: publish reviewed Privacy Policy + Terms with Alibaba/Meta disclosure.")

    # 10 Next steps
    pdf.h1("10. Recommended next steps")
    for b in [
        "1. Legal review of these findings and approve public disclosure wording.",
        "2. Draft/update Privacy Policy and Terms of Use (include Alibaba + Meta + overseas processing).",
        "3. Publish pages on WapApp site; link from signup, billing, and footer.",
        "4. Add visible privacy / grievance contact email and handling process.",
        "5. Confirm vendor contractual safeguards with Alibaba/Meta.",
        "6. Keep product controls maintained (STOP, opt-in, access control, purge).",
        "7. Later (if needed): structured access/erasure request workflow in product.",
    ]:
        pdf.bullet(b)

    pdf.ln(2)
    pdf.callout(
        "Suggested public claim language (for legal review)",
        "We design WapApp to support applicable data-protection obligations by providing consent and "
        "opt-out controls, access security, tenant isolation, and transparent disclosures about how "
        "WhatsApp delivery providers process data. Our customers remain responsible for lawful use "
        "of contact data on their accounts.",
    )

    # 11 Source notes
    pdf.h1("11. Source notes used for this findings pack")
    for b in [
        "WapApp 2.0 product behaviour: opt-in, STOP/START, public unsubscribe, tenancy, 2FA, retention/purge, campaign policy confirmation.",
        "Technical delivery path: Alibaba CAMS endpoint configuration (ap-southeast-1) and Meta WhatsApp delivery chain.",
        "DPDP Act, 2023 - Section 33 and Schedule (monetary penalty ceilings).",
        "DPDP Rules, 2025 / Gazette G.S.R. 846(E) (official Hindi + English notification reviewed for context).",
        "English-only Rules extract and full bilingual Gazette PDF were compared: same Rules; language differs; full Gazette contains both languages.",
    ]:
        pdf.bullet(b)

    pdf.ln(6)
    pdf.set_font("Helvetica", "I", 9)
    pdf.set_text_color(90, 90, 90)
    pdf.set_x(pdf.l_margin)
    pdf.multi_cell(
        pdf.epw,
        5,
        "End of findings document. Before publishing any public policy, replace placeholders "
        "(legal entity name, registered address, privacy email) and obtain legal review.",
    )

    pdf.output(str(OUT))
    return OUT


if __name__ == "__main__":
    print(build())
