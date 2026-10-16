"""Inspect native automatic-closure contracts without claiming an instance test."""
import argparse
import subprocess

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("--repository", required=True)
parser.add_argument("--development-ref", help="Exact optional v25 development commit")
args = parser.parse_args()


def read(ref, path):
    return subprocess.check_output(
        ["git", "-C", args.repository, "show", ref + ":htdocs/" + path],
        encoding="utf-8",
    )


refs = [f"{version}.0.0" for version in range(20, 25)]
if args.development_ref:
    refs.append(args.development_ref)
for ref in refs:
    sha = subprocess.check_output(
        ["git", "-C", args.repository, "rev-parse", ref + "^{commit}"], text=True
    ).strip()
    project = read(ref, "projet/class/project.class.php")
    for symbol in ("function setClose(", "function restrictedProjectArea(", "PROJECT_CLOSE", "STATUS_VALIDATED", "STATUS_CLOSED"):
        assert symbol in project, (ref, symbol)
    invoice = read(ref, "compta/facture/class/facture.class.php")
    for symbol in ("STATUS_CLOSED", "STATUS_ABANDONED", "CLOSECODE_REPLACED"):
        assert symbol in invoice, (ref, symbol)
    order = read(ref, "commande/class/commande.class.php")
    for symbol in ("STATUS_CLOSED", "STATUS_CANCELED"):
        assert symbol in order, (ref, symbol)
    for table, columns in {
        "projet": ("entity", "fk_statut", "datec", "dateo", "fk_user_close", "date_close"),
        "facture": ("fk_projet", "paye", "fk_statut", "entity", "close_code"),
        "commande": ("fk_projet", "fk_statut", "entity"),
        "actioncomm": ("entity", "datep", "datep2", "datec", "fk_project", "fk_element", "elementtype"),
    }.items():
        schema = read(ref, f"install/mysql/tables/llx_{table}.sql")
        for column in columns:
            assert column in schema, (ref, table, column)
    modules = read(ref, "core/modules/DolibarrModules.class.php")
    assert "function insert_cronjobs(" in modules and "function delete_cronjobs(" in modules
    assert "AND test = '1'" in modules, (ref, "native conditional-job retention")
    cron = read(ref, "cron/class/cronjob.class.php")
    assert "->entity = $this->entity" in cron and "lastoutput" in cron
    assert "function loadRights(" in read(ref, "user/class/user.class.php")
    assert "function checkUserAccessToObject(" in read(ref, "core/lib/security.lib.php")
    form = read(ref, "core/class/html.form.class.php")
    selector = form.split("public function select_dolusers(", 1)[1].split("public function select_dolusers_forevent(", 1)[0]
    assert ("forgeSQLFromUniversalSearchCriteria($morefilter" in selector) == (ref != "20.0.0"), (ref, "user-selector USF from v21")
    # Existing native workflow handles order/invoice transitions, not this project rule.
    workflow = read(ref, "core/triggers/interface_20_modWorkflow_WorkflowManager.class.php")
    assert "->setClose(" not in workflow, (ref, "reassess native project automation")
    print(ref, sha, "closure, Agenda, permissions and scheduled-job source contracts present")
